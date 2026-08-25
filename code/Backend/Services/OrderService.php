<?php
// Backend/Services/OrderService.php

require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/Ingredient.php';

/**
 * OrderService – orchestrates POS order placement and void/refund.
 *
 * Responsibilities:
 *   1. Pre-flight stock check (rejects the whole order if any item is short)
 *   2. Atomic DB transaction: insert order header → insert items → deduct stock
 *   3. Void / partial or full refund with stock restoration
 */
class OrderService
{
    private Order      $orderModel;
    private Ingredient $ingredientModel;

    public function __construct()
    {
        $this->orderModel      = new Order();
        $this->ingredientModel = new Ingredient();
    }

    // ─────────────────────────────────────────────────────────────
    //  Place an order
    // ─────────────────────────────────────────────────────────────

    /**
     * Validate stock, then atomically create the order and deduct inventory.
     *
     * @param array $payload  {
     *   table_no, user_id, total_amt, discount_amt, discount_type,
     *   items: [ { menu_id, qty, unit_price, removed_ingredient_ids, addons, notes } ]
     * }
     * @return array  { success, order_id?, message }
     */
    public function place(array $payload): array
    {
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            return ['success' => false, 'message' => 'No items in order.'];
        }

        // ── 1. Pre-flight stock check ────────────────────────────
        $shortages = $this->checkStock($items);
        if (!empty($shortages)) {
            return [
                'success'      => false,
                'out_of_stock' => true,
                'message'      => '⚠️ Out of stock: ' . implode('; ', $shortages),
            ];
        }

        // ── 2. Atomic transaction ────────────────────────────────
        $this->orderModel->beginTransaction();

        try {
            $orderId = $this->orderModel->create([
                'table_no'      => $payload['table_no']      ?? '01',
                'user_id'       => $payload['user_id']       ?? null,
                'cashier_id'    => $payload['user_id']       ?? null,  // alias in case schema uses cashier_id
                'total_amt'     => $payload['total_amt']      ?? 0,
                'discount_amt'  => $payload['discount_amt']   ?? 0,
                'discount_type' => $payload['discount_type']  ?? '',
                'order_type'    => $payload['order_type']     ?? 'Dine In',
                'pay_method'    => $payload['pay_method']     ?? 'Cash',
                'cash_tendered' => $payload['cash_tendered']  ?? 0,
            ]);

            foreach ($items as $item) {
                $menuId     = (int)   $item['menu_id'];
                $qty        = (int)   $item['qty'];
                $unitPrice  = (float) $item['unit_price'];
                $rawRemoved = $item['removed_ingredient_ids'] ?? [];

                [$removedIds, $removedNames] = $this->parseRemovedIngredients($rawRemoved);

                $this->orderModel->addItem([
                    'order_id'      => $orderId,
                    'menu_id'       => $menuId,
                    'qty'           => $qty,
                    'unit_price'    => $unitPrice,
                    'removed_ids'   => $removedIds,
                    'removed_names' => $removedNames,
                    'addons'        => (string) ($item['addons'] ?? ''),
                    'notes'         => (string) ($item['notes']  ?? ''),
                ]);

                $this->deductIngredients($menuId, $qty, $removedIds);
            }

            $this->orderModel->commit();

            return [
                'success'  => true,
                'order_id' => $orderId,
                'message'  => 'Order placed and inventory updated.',
            ];

        } catch (Exception $e) {
            $this->orderModel->rollback();
            return ['success' => false, 'message' => 'Order failed: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Void / refund
    // ─────────────────────────────────────────────────────────────

    /**
     * Void an order entirely or refund specific items.
     *
     * @param array $payload  {
     *   action: 'void'|'refund',
     *   order_id: int,
     *   reason: string,
     *   refund_items?: [ { menu_id, qty } ]   ← partial refund only
     *   created_by: string
     * }
     */
    public function voidOrRefund(array $payload): array
    {
        $action   = $payload['action']   ?? '';
        $orderId  = (int) ($payload['order_id'] ?? 0);
        $reason   = $payload['reason']   ?? '';
        $actor    = $payload['created_by'] ?? 'unknown';

        if (!in_array($action, ['void', 'refund'], true)) {
            return ['success' => false, 'message' => 'Invalid action.'];
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            return ['success' => false, 'message' => "Order #$orderId not found."];
        }
        if (in_array($order['status'], ['voided', 'refunded'], true)) {
            return [
                'success' => false,
                'message' => "Order #$orderId is already {$order['status']}.",
            ];
        }

        $allItems = $this->orderModel->getItems($orderId);

        // Determine which items to restore
        [$refundItems, $refundAmt] = $this->resolveRefundItems(
            $action,
            $allItems,
            $payload['refund_items'] ?? [],
            (float) $order['total_amt']
        );

        if (isset($refundItems['error'])) {
            return ['success' => false, 'message' => $refundItems['error']];
        }

        // Determine new status
        $isFullVoid   = ($action === 'void');
        $isFullRefund = ($action === 'refund' && empty($payload['refund_items']));
        $newStatus    = $isFullVoid ? 'voided' : ($isFullRefund ? 'refunded' : 'partial_refund');

        // Atomic transaction
        $this->orderModel->beginTransaction();

        try {
            $this->restoreIngredients($refundItems);
            $this->orderModel->updateStatus($orderId, $newStatus);

            $logId = $this->orderModel->logRefund([
                'order_id'   => $orderId,
                'action'     => $action,
                'refund_amt' => $refundAmt,
                'reason'     => $reason,
                'items'      => $refundItems,
                'created_by' => $actor,
            ]);

            $this->orderModel->commit();

            $msg = $isFullVoid
                ? "Order #$orderId voided. ₱" . number_format($refundAmt, 2) . ' reversed.'
                : 'Refund of ₱' . number_format($refundAmt, 2) . " processed for Order #$orderId.";

            return [
                'success'       => true,
                'action'        => $action,
                'order_id'      => $orderId,
                'new_status'    => $newStatus,
                'refund_amt'    => $refundAmt,
                'refund_log_id' => $logId,
                'message'       => $msg,
            ];

        } catch (Exception $e) {
            $this->orderModel->rollback();
            return ['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Aggregate ingredient needs across the full order and return
     * a list of human-readable shortage strings (empty = all good).
     */
    private function checkStock(array $items): array
    {
        $needs = [];

        foreach ($items as $item) {
            $menuId     = (int) $item['menu_id'];
            $qty        = (int) $item['qty'];
            $rawRemoved = $item['removed_ingredient_ids'] ?? [];
            $removedIds = array_map('intval', array_map(
                fn($r) => is_array($r) ? ($r['id'] ?? 0) : $r,
                $rawRemoved
            ));

            $requirements = $this->ingredientModel->getRequirementsForMenu($menuId);

            foreach ($requirements as $req) {
                $id = (int) $req['id'];
                if (in_array($id, $removedIds, true)) {
                    continue;
                }
                $required = (float) $req['qty_needed'] * $qty;

                if (!isset($needs[$id])) {
                    $needs[$id] = [
                        'name'      => $req['name'],
                        'unit'      => $req['unit'],
                        'available' => (float) $req['stock_qty'],
                        'needed'    => 0,
                    ];
                }
                $needs[$id]['needed'] += $required;
            }
        }

        $shortages = [];
        foreach ($needs as $ing) {
            if ($ing['needed'] > $ing['available']) {
                $shortages[] = sprintf(
                    '"%s" needs %.2f %s but only %.2f %s left',
                    $ing['name'],
                    $ing['needed'],
                    $ing['unit'],
                    $ing['available'],
                    $ing['unit']
                );
            }
        }
        return $shortages;
    }

    /** Deduct ingredient stock for one menu item × qty, skipping removed IDs. */
    private function deductIngredients(int $menuId, int $qty, array $removedIds): void
    {
        $requirements = $this->ingredientModel->getRequirementsForMenu($menuId);
        foreach ($requirements as $req) {
            $ingId = (int) $req['id'];
            if (in_array($ingId, $removedIds, true)) {
                continue;
            }
            $this->ingredientModel->deduct($ingId, (float) $req['qty_needed'] * $qty);
        }
    }

    /** Restore ingredient stock for a list of refund items. */
    private function restoreIngredients(array $refundItems): void
    {
        foreach ($refundItems as $ri) {
            $menuId     = (int) $ri['menu_id'];
            $qty        = (int) $ri['qty'];
            $removedIds = array_map('intval', $ri['removed_ingredient_ids'] ?? []);

            $requirements = $this->ingredientModel->getRequirementsForMenu($menuId);
            foreach ($requirements as $req) {
                $ingId = (int) $req['id'];
                if (in_array($ingId, $removedIds, true)) {
                    continue;
                }
                $this->ingredientModel->restore($ingId, (float) $req['qty_needed'] * $qty);
            }
        }
    }

    /**
     * Determine which items to refund and calculate the total refund amount.
     *
     * @return array  [ $refundItems[], $refundAmt ]  or [ ['error' => msg], 0 ]
     */
    private function resolveRefundItems(
        string $action,
        array $allItems,
        array $requestedItems,
        float $orderTotal
    ): array {
        if ($action === 'void' || empty($requestedItems)) {
            // Void or full refund → return everything
            $items = array_map(fn($oi) => [
                'menu_id'                => (int)   $oi['menu_id'],
                'qty'                    => (int)   $oi['qty'],
                'removed_ingredient_ids' => $oi['removed_ingredient_ids'],
            ], $allItems);
            return [$items, $orderTotal];
        }

        // Partial refund
        $orderedMap = [];
        foreach ($allItems as $oi) {
            $orderedMap[(int) $oi['menu_id']] = $oi;
        }

        $refundItems = [];
        $refundAmt   = 0.0;

        foreach ($requestedItems as $ri) {
            $mid  = (int) $ri['menu_id'];
            $rqty = (int) $ri['qty'];

            if (!isset($orderedMap[$mid])) {
                return [['error' => "Item menu_id=$mid not in order."], 0];
            }
            if ($rqty > (int) $orderedMap[$mid]['qty']) {
                return [[
                    'error' => "Refund qty ($rqty) exceeds ordered qty "
                        . "({$orderedMap[$mid]['qty']}) for {$orderedMap[$mid]['menu_name']}.",
                ], 0];
            }
            $refundItems[] = [
                'menu_id'                => $mid,
                'qty'                    => $rqty,
                'removed_ingredient_ids' => $orderedMap[$mid]['removed_ingredient_ids'],
            ];
            $refundAmt += (float) $orderedMap[$mid]['unit_price'] * $rqty;
        }

        return [$refundItems, $refundAmt];
    }

    /**
     * Normalise the removed_ingredient_ids field from the POS payload.
     * Accepts both plain id arrays and [{id, name}] object arrays.
     *
     * @return array  [ $ids[], $names[] ]
     */
    private function parseRemovedIngredients(array $raw): array
    {
        $ids   = [];
        $names = [];
        foreach ($raw as $r) {
            if (is_array($r)) {
                $ids[]   = (int)    ($r['id']   ?? 0);
                $names[] = (string) ($r['name'] ?? '');
            } else {
                $ids[] = (int) $r;
            }
        }
        return [$ids, $names];
    }
}