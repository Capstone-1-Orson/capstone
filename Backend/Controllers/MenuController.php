<?php
// Backend/Controllers/MenuController.php

/**
 * MenuController – handles all menu-management HTTP actions.
 *
 * Replaces: Backend/menu_process.php
 *
 * Routes:
 *   GET  ?action=get_ingredients&id=N  → JSON ingredient list for edit modal
 *   POST save_menu                     → add new menu item
 *   POST update_menu                   → edit existing menu item
 *   POST action=delete                 → delete / soft-delete menu item
 */

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/Menu.php';
require_once __DIR__ . '/../Services/ImageUploadService.php';

class MenuController
{
    private Menu               $model;
    private ImageUploadService $uploader;
    private string             $redirectBack = '../../Frontend/ADMIN/menu-management.php';

    public function __construct()
    {
        Auth::requireAdmin('../../lockscreen.html');
        $this->model    = new Menu();
        $this->uploader = new ImageUploadService('menu');
    }

    public function handle(): void
    {
        // AJAX: return ingredient list as JSON
        if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
            ($_GET['action'] ?? '') === 'get_ingredients'
        ) {
            $this->getIngredients();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect();
        }

        if (isset($_POST['save_menu']))                         { $this->create(); return; }
        if (isset($_POST['update_menu']))                       { $this->update(); return; }
        if (($_POST['action'] ?? '') === 'delete')              { $this->delete(); return; }

        $this->redirect();
    }

    // ─────────────────────────────────────────────────────────────

    private function getIngredients(): never
    {
        header('Content-Type: application/json');
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }

        $rows = $this->model->getIngredients($id);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    }

    private function create(): never
    {
        $data = $this->collectFormData();

        if (!$data['name'] || !$data['category'] || $data['price'] <= 0) {
            return $this->fail('Name, category, and a valid price are required.');
        }

        try {
            $data['image'] = $this->uploader->handle($_FILES['image'] ?? null);
            $newId         = $this->model->create($data);

            if ($newId) {
                $this->model->syncIngredients($newId, $this->decodeIngredients());
                Session::flashSuccess("\"{$data['name']}\" added to the menu successfully.");
            } else {
                Session::flashError('Failed to add menu item.');
            }
        } catch (RuntimeException $e) {
            Session::flashError($e->getMessage());
        }

        $this->redirect();
    }

    private function update(): never
    {
        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->collectFormData();

        if (!$id || !$data['name'] || !$data['category'] || $data['price'] <= 0) {
            return $this->fail('Name, category, and a valid price are required.');
        }

        try {
            $existingImg   = trim($_POST['existing_image'] ?? '');
            $newPath       = $this->uploader->handle($_FILES['image'] ?? null);

            if ($newPath) {
                $this->uploader->delete($existingImg);
                $data['image'] = $newPath;
            } else {
                $data['image'] = $existingImg ?: null;
            }

            if ($this->model->update($id, $data)) {
                $this->model->syncIngredients($id, $this->decodeIngredients());
                Session::flashSuccess("\"{$data['name']}\" updated successfully.");
            } else {
                Session::flashError('Failed to update menu item.');
            }
        } catch (RuntimeException $e) {
            Session::flashError($e->getMessage());
        }

        $this->redirect();
    }

    private function delete(): never
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            return $this->fail('Invalid item ID.');
        }

        if ($this->model->hasOrderHistory($id)) {
            // Soft delete — preserve history
            $this->model->softDelete($id);
            Session::flashSuccess('Item has order history and was hidden (set to Inactive) instead of deleted.');
        } else {
            $item = $this->model->findById($id);
            $this->model->delete($id);
            if ($item && !empty($item['image'])) {
                $this->uploader->delete($item['image']);
            }
            Session::flashSuccess('Menu item deleted successfully.');
        }

        $this->redirect();
    }

    // ─────────────────────────────────────────────────────────────

    private function collectFormData(): array
    {
        return [
            'name'         => trim($_POST['name']         ?? ''),
            'category'     => trim($_POST['category']     ?? ''),
            'price'        => (float) ($_POST['price']    ?? 0),
            'is_available' => (int)   ($_POST['is_available'] ?? 1),
            'description'  => trim($_POST['description']  ?? ''),
            'image'        => null,   // set by upload handler
        ];
    }

    /** Decode the hidden ingredients_json field into an array. */
    private function decodeIngredients(): array
    {
        $raw  = $_POST['ingredients_json'] ?? '[]';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
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

(new MenuController())->handle();