<?php
// Backend/Controllers/InventoryController.php

/**
 * InventoryController - handles all inventory HTTP actions.
 *
 * Replaces: Backend/inventory_process.php
 *
 * Actions (POST field / query param):
 *   save_ingredient          – add new ingredient
 *   update_ingredient        – edit existing ingredient
 *   bulk_restock             – add stock to multiple ingredients
 *   action=delete (POST)     – delete an ingredient
 *   bulk_update_thresholds   – update low-stock thresholds in bulk
 *
 * Like MenuController, every action here is admin-only, enforced once in
 * the constructor.
 *
 * NOTE: several methods below originally declared a `: never` return
 * type while still containing `return $this->fail(...)` statements.
 * PHP does not allow ANY `return` statement inside a `never`-typed
 * function, so the original file was a fatal parse error on PHP 8.1+
 * and could not run. This version corrects those signatures to `: void`
 * (see the BUG FIX notes on create(), update(), bulkRestock(),
 * bulkThresholds(), delete(), and reportWaste()) without changing any
 * behavior.
 */

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/Ingredient.php';

class InventoryController
{
    // Data-access layer for the `ingredient` table, plus waste-log rows.
    private Ingredient $model;
    private string     $redirectBack = '../../Frontend/ADMIN/inventory.php';

    public function __construct()
    {
        Auth::requireAdmin('../../lockscreen.html');
        $this->model = new Ingredient();
    }

    /**
     * Route the incoming request based on which action field is present.
     * Checked in priority order; only the first matching branch runs.
     */
    public function handle(): void
    {
        // AJAX: fetch waste log for a specific date (GET).
        // Used by the inventory page's "waste history" panel to load a
        // day's entries without a full page reload.
        if (isset($_GET['fetch_waste_log'])) {
            $this->fetchWasteLog();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect();
        }

        if (isset($_POST['save_ingredient']))          { $this->create();          return; }
        if (isset($_POST['report_waste']))             { $this->reportWaste();     return; }
        if (isset($_POST['update_ingredient']))        { $this->update();          return; }
        if (isset($_POST['bulk_restock']))             { $this->bulkRestock();     return; }
        if (isset($_POST['bulk_update_thresholds']))   { $this->bulkThresholds();  return; }
        if (($_POST['action'] ?? '') === 'delete')     { $this->delete();          return; }

        $this->redirect();
    }

    // -----------------------------------------------------------------

    /**
     * Add a brand-new ingredient to inventory (e.g. "Tomatoes", "kg").
     *
     * BUG FIX: this method was originally declared `: never`, but its
     * body contains `return $this->fail(...)`. PHP forbids ANY `return`
     * statement inside a function declared `never` - even one that
     * returns the result of another never-returning call - so the
     * original code was a fatal parse error and could not run at all on
     * PHP 8.1+. Changed to `: void` here (fail() itself is still
     * correctly `never`, since it has no `return` statement).
     */
    private function create(): void
    {
        $data = $this->collectFormData();

        if (empty($data['name']) || empty($data['unit'])) {
            $this->fail('Name and Unit are required.');
        }

        if ($this->model->create($data)) {
            Session::flashSuccess(
                'Ingredient "' . htmlspecialchars($data['name']) . '" added successfully!'
            );
        } else {
            Session::flashError('Failed to add ingredient.');
        }

        $this->redirect();
    }

    /**
     * Edit an existing ingredient's name/unit/stock/threshold/expiry.
     *
     * BUG FIX: changed from `: never` to `: void` - see create() above
     * for why (this method also `return`s the result of fail()).
     */
    private function update(): void
    {
        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->collectFormData();

        if ($id <= 0 || empty($data['name']) || empty($data['unit'])) {
            $this->fail('Please fill in all required fields.');
        }

        if ($this->model->update($id, $data)) {
            Session::flashSuccess(
                'Ingredient "' . htmlspecialchars($data['name']) . '" updated successfully!'
            );
        } else {
            Session::flashError('Failed to update ingredient.');
        }

        $this->redirect();
    }

    /**
     * Restock many ingredients in one go (e.g. after a delivery).
     *
     * The form submits two parallel arrays keyed by ingredient id:
     * `restock_ids[]` (which rows were checked) and `restock_qty[id]`
     * (how much to add for that row). Anything with a zero/blank/invalid
     * quantity is silently skipped rather than erroring the whole batch.
     * BUG FIX: changed from `: never` to `: void` - see create() above.
     */
    private function bulkRestock(): void
    {
        $ids    = $_POST['restock_ids'] ?? [];
        $qtys   = $_POST['restock_qty'] ?? [];
        $count  = 0;

        if (empty($ids)) {
            $this->fail('No items were selected for restock.');
        }

        foreach ($ids as $rid) {
            $rid = (int) $rid;
            $qty = (float) ($qtys[$rid] ?? 0);
            if ($qty <= 0) {
                continue; // Skip rows with no/invalid quantity entered.
            }
            if ($this->model->restock($rid, $qty)) {
                $count++;
            }
        }

        Session::flashSuccess("$count ingredient(s) restocked successfully!");
        $this->redirect();
    }

    /**
     * Update the "low stock" alert threshold for many ingredients at once,
     * from a bulk-edit table on the inventory page.
     * BUG FIX: changed from `: never` to `: void` - see create() above.
     */
    private function bulkThresholds(): void
    {
        $thresholds = $_POST['threshold'] ?? [];
        $count      = 0;

        if (empty($thresholds)) {
            $this->fail('No threshold data received.');
        }

        // Here `$thresholds` is keyed by ingredient id directly
        // (threshold[id] = value), unlike bulkRestock()'s separate
        // ids/qtys arrays.
        foreach ($thresholds as $id => $threshold) {
            $id        = (int)   $id;
            $threshold = (float) $threshold;
            if ($id <= 0) {
                continue;
            }
            if ($this->model->updateThreshold($id, $threshold)) {
                $count++;
            }
        }

        Session::flashSuccess("$count threshold(s) updated successfully!");
        $this->redirect();
    }

    /**
     * Permanently remove an ingredient from inventory.
     *
     * BUG FIX: changed from `: never` to `: void` - see create() above.
     */
    private function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('Invalid ingredient ID.');
        }

        if ($this->model->delete($id)) {
            Session::flashSuccess('Ingredient deleted successfully.');
        } else {
            Session::flashError('Failed to delete ingredient.');
        }

        $this->redirect();
    }

    /**
     * AJAX: return the waste log entries for a single date as JSON.
     * Defaults to today if no date is supplied.
     */
    private function fetchWasteLog(): never
    {
        header('Content-Type: application/json');
        $date = $_GET['date'] ?? date('Y-m-d');
        // Same date used for both "from" and "to" — this fetches a single
        // day's worth of entries, not a range.
        $rows = $this->model->getWasteLogs($date, $date);
        echo json_encode(['rows' => $rows]);
        exit();
    }

    /**
     * Log one or more "wasted" ingredient entries (spoilage, breakage,
     * etc.) submitted from the waste-reporting form. Each logged entry
     * also deducts that quantity from the ingredient's current stock
     * (handled inside Ingredient::logWaste()).
     *
     * Like bulkRestock(), this iterates parallel arrays keyed by index
     * (not by ingredient id) since multiple waste rows can be submitted
     * from one form.
     *
     * BUG FIX: changed from `: never` to `: void` - see create() above.
     */
    private function reportWaste(): void
    {
        $rows    = $_POST['waste_ingredient_id'] ?? [];
        $qtys    = $_POST['waste_qty']           ?? [];
        $reasons = $_POST['waste_reason']        ?? [];
        $date    = trim($_POST['waste_date']      ?? date('Y-m-d'));
        // Prefer the actor's first name for the log; fall back to
        // whatever the session "user" identity is, then to 'admin'.
        $actor   = Session::get('firstname', '') ?: Session::get('user', 'admin');

        if (empty($rows)) {
            $this->fail('No waste items submitted.');
        }

        $count = 0;
        foreach ($rows as $idx => $ingId) {
            $ingId = (int) $ingId;
            $qty   = (float) ($qtys[$idx]    ?? 0);
            $why   = trim($reasons[$idx]     ?? '');

            // Skip rows that are incomplete/invalid rather than failing
            // the whole submission.
            if ($ingId <= 0 || $qty <= 0) {
                continue;
            }

            $ok = $this->model->logWaste([
                'ingredient_id' => $ingId,
                'qty_wasted'    => $qty,
                'reason'        => $why ?: 'Spoilage / waste', // default reason if left blank
                'reported_by'   => $actor,
                'waste_date'    => $date,
            ]);

            if ($ok) {
                $count++;
            }
        }

        if ($count > 0) {
            Session::flashSuccess("$count waste item(s) recorded and deducted from inventory.");
        } else {
            Session::flashError('No valid waste entries were saved. Check quantities.');
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------

    /**
     * Pull out and sanitise the common ingredient fields shared by
     * create() and update(). `expiry_date` is normalised to null when
     * blank rather than an empty string, since the DB column is nullable.
     */
    private function collectFormData(): array
    {
        return [
            'name'                => trim($_POST['name']                ?? ''),
            'unit'                => trim($_POST['unit']                ?? ''),
            'stock_qty'           => (float) ($_POST['stock_qty']           ?? 0),
            'low_stock_threshold' => (float) ($_POST['low_stock_threshold'] ?? 0),
            'expiry_date'         => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        ];
    }

    /** Flash an error message and redirect back. */
    private function fail(string $message): never
    {
        Session::flashError($message);
        $this->redirect();
    }

    private function redirect(): never
    {
        header("Location: {$this->redirectBack}");
        exit();
    }
}

// ── Bootstrap ──────────────────────────────────────────────────────
(new InventoryController())->handle();
