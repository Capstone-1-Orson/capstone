<?php
// Backend/Controllers/InventoryController.php

/**
 * InventoryController – handles all inventory HTTP actions.
 *
 * Replaces: Backend/inventory_process.php
 *
 * Actions (POST field / query param):
 *   save_ingredient          – add new ingredient
 *   update_ingredient        – edit existing ingredient
 *   bulk_restock             – add stock to multiple ingredients
 *   action=delete (POST)     – delete an ingredient
 *   bulk_update_thresholds   – update low-stock thresholds in bulk
 */

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/Ingredient.php';

class InventoryController
{
    private Ingredient $model;
    private string     $redirectBack = '../../Frontend/ADMIN/inventory.php';

    public function __construct()
    {
        Auth::requireAdmin('../../lockscreen.html');
        $this->model = new Ingredient();
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect();
        }

        if (isset($_POST['save_ingredient']))          { $this->create();          return; }
        if (isset($_POST['update_ingredient']))        { $this->update();          return; }
        if (isset($_POST['bulk_restock']))             { $this->bulkRestock();     return; }
        if (isset($_POST['bulk_update_thresholds']))   { $this->bulkThresholds();  return; }
        if (($_POST['action'] ?? '') === 'delete')     { $this->delete();          return; }

        $this->redirect();
    }

    // ─────────────────────────────────────────────────────────────

    private function create(): never
    {
        $data = $this->collectFormData();

        if (empty($data['name']) || empty($data['unit'])) {
            return $this->fail('Name and Unit are required.');
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

    private function update(): never
    {
        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->collectFormData();

        if ($id <= 0 || empty($data['name']) || empty($data['unit'])) {
            return $this->fail('Please fill in all required fields.');
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

    private function bulkRestock(): never
    {
        $ids    = $_POST['restock_ids'] ?? [];
        $qtys   = $_POST['restock_qty'] ?? [];
        $count  = 0;

        if (empty($ids)) {
            return $this->fail('No items were selected for restock.');
        }

        foreach ($ids as $rid) {
            $rid = (int) $rid;
            $qty = (float) ($qtys[$rid] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if ($this->model->restock($rid, $qty)) {
                $count++;
            }
        }

        Session::flashSuccess("$count ingredient(s) restocked successfully!");
        $this->redirect();
    }

    private function bulkThresholds(): never
    {
        $thresholds = $_POST['threshold'] ?? [];
        $count      = 0;

        if (empty($thresholds)) {
            return $this->fail('No threshold data received.');
        }

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

    private function delete(): never
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            return $this->fail('Invalid ingredient ID.');
        }

        if ($this->model->delete($id)) {
            Session::flashSuccess('Ingredient deleted successfully.');
        } else {
            Session::flashError('Failed to delete ingredient.');
        }

        $this->redirect();
    }

    // ─────────────────────────────────────────────────────────────

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

(new InventoryController())->handle();