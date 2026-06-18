<?php
// Backend/Controllers/SupplierController.php

/**
 * SupplierController - handles all supplier CRUD actions.
 *
 * Replaces: Backend/supplier_process.php
 *
 * Routes (action = $_REQUEST['action']):
 *   add     – create supplier
 *   update  – edit supplier
 *   delete  – remove supplier (id via GET)
 *
 * Stylistic note: this is the only controller in the set that uses a
 * `match` expression for routing instead of a chain of `if (isset(...))`
 * checks, and the only one whose action handlers return `void` rather
 * than `never` (even though every branch still ends by redirecting).
 */

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/Supplier.php';

class SupplierController
{
    private Supplier $model;
    private string   $redirectBack = '../../Frontend/ADMIN/suppliers.php';

    public function __construct()
    {
        Auth::requireAdmin('../../lockscreen.html');
        $this->model = new Supplier();
    }

    /**
     * Route based on a single `action` parameter, which - unlike the
     * other controllers - is read from $_REQUEST rather than $_POST,
     * meaning it can be supplied via either the query string or the
     * POST body (used by delete(), which reads its id from $_GET while
     * `action` itself can still arrive either way).
     */
    public function handle(): void
    {
        $action = $_REQUEST['action'] ?? '';

        match ($action) {
            'add'    => $this->create(),
            'update' => $this->update(),
            'delete' => $this->delete(),
            default  => $this->fail('Unknown action.'),
        };
    }

    // -----------------------------------------------------------------

    /** Create a new supplier record. */
    private function create(): void
    {
        $data = $this->collectFormData();

        if ($data['name'] === '' || $data['category'] === '') {
            $this->fail('Supplier name and category are required.');
        }

        if ($this->model->create($data)) {
            Session::flashSuccess("Supplier \"{$data['name']}\" added successfully.");
        } else {
            Session::flashError("Failed to add supplier.");
        }

        $this->redirect();
    }

    /** Edit an existing supplier record. */
    private function update(): void
    {
        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->collectFormData();

        if ($id <= 0 || $data['name'] === '' || $data['category'] === '') {
            $this->fail('Invalid data submitted for update.');
        }

        if ($this->model->update($id, $data)) {
            Session::flashSuccess("Supplier \"{$data['name']}\" updated successfully.");
        } else {
            Session::flashError("Failed to update supplier.");
        }

        $this->redirect();
    }

    /**
     * Delete a supplier. Unlike create/update, the id here comes from
     * $_GET - this action is wired up as a plain link (e.g. a "Delete"
     * button with an href), not a form POST.
     */
    private function delete(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('Invalid supplier ID.');
        }

        // Look the supplier up first so we can reference its name in the
        // success message even after the row is gone.
        $supplier = $this->model->findById($id);
        $name     = $supplier['name'] ?? 'Unknown';

        if ($this->model->delete($id)) {
            Session::flashSuccess("Supplier \"$name\" deleted successfully.");
        } else {
            Session::flashError("Failed to delete supplier.");
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------

    /**
     * Collect and sanitise supplier fields shared by create/update.
     * `status` is passed through a model-level sanitiser (rather than a
     * plain trim) since it must be constrained to a known set of values
     * (e.g. 'Active' / 'Inactive') rather than free text.
     */
    private function collectFormData(): array
    {
        return [
            'name'           => trim($_POST['name']           ?? ''),
            'category'       => trim($_POST['category']       ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'phone'          => trim($_POST['phone']          ?? ''),
            'email'          => trim($_POST['email']          ?? ''),
            'address'        => trim($_POST['address']        ?? ''),
            'notes'          => trim($_POST['notes']          ?? ''),
            'status'         => Supplier::sanitiseStatus($_POST['status'] ?? 'Active'),
        ];
    }

    /** Flash an error message and redirect back. */
    private function fail(string $message): void
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
(new SupplierController())->handle();
