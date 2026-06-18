<?php
// Backend/Controllers/MenuController.php

/**
 * MenuController - handles all menu-management HTTP actions.
 *
 * Replaces: Backend/menu_process.php
 *
 * Routes:
 *   GET  ?action=get_ingredients&id=N  -> JSON ingredient list for edit modal
 *   POST save_menu                     -> add new menu item
 *   POST update_menu                   -> edit existing menu item
 *   POST action=delete                 -> delete / soft-delete menu item
 *
 * Every action in this controller is admin-only - enforced once, up
 * front, in the constructor via Auth::requireAdmin(), rather than
 * repeated in every method.
 */

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/Menu.php';
require_once __DIR__ . '/../Services/ImageUploadService.php';

class MenuController
{
    // Data-access layer for the `menu` table and its ingredient links.
    private Menu               $model;

    // Handles saving/validating/deleting uploaded menu photos so this
    // controller doesn't need to know about file paths or mime checks.
    private ImageUploadService $uploader;

    // Almost every action in this controller ends by redirecting back to
    // the menu management page (success or failure - flash messages
    // communicate the outcome), so the target URL is stored once here.
    private string             $redirectBack = '../../Frontend/ADMIN/menu-management.php';

    public function __construct()
    {
        // Bounce to the lockscreen immediately if there's no valid admin
        // session - nothing below this line runs for unauthenticated users.
        Auth::requireAdmin('../../lockscreen.html');
        $this->model    = new Menu();
        // 'menu' tells the uploader which subfolder/prefix to store images
        // under (as opposed to e.g. 'staff' photos in StaffController).
        $this->uploader = new ImageUploadService('menu');
    }

    /**
     * Route the incoming request to the correct handler based on which
     * action indicator is present.
     */
    public function handle(): void
    {
        // AJAX: return ingredient list as JSON.
        // This is the one GET-based action - used by the "Edit" modal to
        // pre-populate the ingredient checklist for a given menu item,
        // without needing a full page reload.
        if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
            ($_GET['action'] ?? '') === 'get_ingredients'
        ) {
            $this->getIngredients();
            return;
        }

        // Every other action is a normal form POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect();
        }

        if (isset($_POST['save_menu']))                         { $this->create(); return; }
        if (isset($_POST['update_menu']))                       { $this->update(); return; }
        if (($_POST['action'] ?? '') === 'delete')              { $this->delete(); return; }

        // Unrecognised POST - just go back to the listing page.
        $this->redirect();
    }

    // -----------------------------------------------------------------

    /**
     * AJAX handler: given a menu item id, return the list of ingredients
     * (and quantities) currently linked to it, as JSON.
     */
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

    /**
     * Create a brand-new menu item from the "Add Menu Item" form.
     */
    private function create(): never
    {
        $data = $this->collectFormData();

        // Server-side validation mirrors whatever client-side checks
        // exist, but is the one that actually matters - never trust the
        // browser alone.
        if (!$data['name'] || !$data['category'] || $data['price'] <= 0) {
            $this->fail('Name, category, and a valid price are required.');
        }

        try {
            // Image upload is optional - $uploader->handle() returns null
            // (or similar) if no file was actually submitted.
            $data['image'] = $this->uploader->handle($_FILES['image'] ?? null);
            $newId         = $this->model->create($data);

            if ($newId) {
                // Ingredients are stored as a separate linking table, so
                // they're synced in a second step once we know the new
                // menu item's id.
                $this->model->syncIngredients($newId, $this->decodeIngredients());
                Session::flashSuccess("\"{$data['name']}\" added to the menu successfully.");
            } else {
                Session::flashError('Failed to add menu item.');
            }
        } catch (RuntimeException $e) {
            // Thrown by the uploader for things like "file too large" or
            // "not a valid image type" - surfaced to the user as a flash
            // error rather than a fatal crash.
            Session::flashError($e->getMessage());
        }

        $this->redirect();
    }

    /**
     * Update an existing menu item from the "Edit Menu Item" form.
     */
    private function update(): never
    {
        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->collectFormData();

        if (!$id || !$data['name'] || !$data['category'] || $data['price'] <= 0) {
            $this->fail('Name, category, and a valid price are required.');
        }

        try {
            // The edit form always sends `existing_image` (the current
            // image path) as a hidden field, so we know what to fall back
            // to / clean up depending on whether a new file was uploaded.
            $existingImg   = trim($_POST['existing_image'] ?? '');
            $newPath       = $this->uploader->handle($_FILES['image'] ?? null);

            if ($newPath) {
                // A new image was uploaded - delete the old file from disk
                // so orphaned images don't pile up, then use the new path.
                $this->uploader->delete($existingImg);
                $data['image'] = $newPath;
            } else {
                // No new upload - keep whatever image was already there.
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

    /**
     * Delete a menu item - but only if it's safe to do so outright.
     *
     * Menu items that have already been ordered (i.e. referenced by past
     * orders) cannot be hard-deleted without breaking order history /
     * foreign-key integrity, so those get "soft deleted" (marked inactive)
     * instead of removed from the table.
     */
    private function delete(): never
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->fail('Invalid item ID.');
        }

        $item = $this->model->findById($id);
        if (!$item) {
            $this->fail('Menu item not found.');
        }

        if ($this->model->hasOrderHistory($id)) {
            // Soft delete — item is linked to orders, preserve history.
            if ($this->model->softDelete($id)) {
                Session::flashSuccess("\"{$item['name']}\" has order history — it has been set to Inactive instead of permanently deleted.");
            } else {
                Session::flashError('Failed to deactivate menu item. Please try again.');
            }
        } else {
            // Hard delete — no order history, safe to remove.
            try {
                if ($this->model->delete($id)) {
                    // Only clean up the image file after the DB row is
                    // confirmed gone, so we never delete a photo that's
                    // still referenced.
                    if (!empty($item['image'])) {
                        $this->uploader->delete($item['image']);
                    }
                    Session::flashSuccess("\"{$item['name']}\" deleted successfully.");
                } else {
                    // delete() returned false — likely a DB constraint; fall back to soft-delete.
                    $this->model->softDelete($id);
                    Session::flashSuccess("\"{$item['name']}\" could not be fully deleted (it may be referenced elsewhere) and was set to Inactive instead.");
                }
            } catch (\Exception $e) {
                // FK constraint or other DB error — fall back to soft-delete.
                // (Belt-and-suspenders: hasOrderHistory() should normally
                // catch this case already, but a DB-level constraint
                // violation is handled gracefully here too, just in case.)
                $this->model->softDelete($id);
                Session::flashSuccess("\"{$item['name']}\" could not be fully deleted and was set to Inactive instead.");
            }
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------

    /**
     * Pull out and sanitise the common fields shared by create/update.
     * `image` is deliberately left null here - it gets set later by
     * whichever caller actually handles the file upload logic.
     */
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

    /**
     * Decode the hidden ingredients_json field into an array.
     * The frontend builds this field as a JSON string listing the
     * ingredients (and amounts) selected for the menu item; here we turn
     * it back into a PHP array for Menu::syncIngredients().
     */
    private function decodeIngredients(): array
    {
        $raw  = $_POST['ingredients_json'] ?? '[]';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /** Flash an error message and redirect back - used for all validation failures. */
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
(new MenuController())->handle();
