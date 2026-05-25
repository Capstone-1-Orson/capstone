<?php
// Frontend/Core/SupplierView.php

require_once __DIR__ . '/View.php';

/**
 * SupplierView – loads all data needed by Frontend/ADMIN/suppliers.php.
 *
 * Usage at the top of suppliers.php:
 *   require_once '../../Frontend/Core/SupplierView.php';
 *   $view = new SupplierView();
 */
class SupplierView extends View
{
    public int   $totalSuppliers;
    public int   $activeSuppliers;
    public int   $inactiveSuppliers;
    public int   $categories;

    public array $suppliers = [];

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->load();
    }

    private function load(): void
    {
        $this->totalSuppliers    = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM suppliers")['c']                          ?? 0);
        $this->activeSuppliers   = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM suppliers WHERE status = 'Active'")['c'] ?? 0);
        $this->inactiveSuppliers = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM suppliers WHERE status = 'Inactive'")['c']?? 0);
        $this->categories        = (int) ($this->fetchOne("SELECT COUNT(DISTINCT category) AS c FROM suppliers")['c']         ?? 0);

        $this->suppliers = $this->fetchAll('SELECT * FROM suppliers ORDER BY name ASC');
    }
}
