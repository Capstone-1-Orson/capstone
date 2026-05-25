<?php
// Backend/Controllers/SupplierController.php

/**
 * SupplierController – handles all supplier CRUD actions.
 *
 * Replaces: Backend/supplier_process.php
 *
 * Routes (action = $_REQUEST['action']):
 *   add     – create supplier
 *   update  – edit supplier
 *   delete  – remove supplier (id via GET)
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

    // ─────────────────────────────────────────────────────────────

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

    private function delete(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('Invalid supplier ID.');
        }

        $supplier = $this->model->findById($id);
        $name     = $supplier['name'] ?? 'Unknown';

        if ($this->model->delete($id)) {
            Session::flashSuccess("Supplier \"$name\" deleted successfully.");
        } else {
            Session::flashError("Failed to delete supplier.");
        }

        $this->redirect();
    }

    // ─────────────────────────────────────────────────────────────

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

(new SupplierController())->handle();