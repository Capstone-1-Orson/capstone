<?php
// Frontend/Core/MenuView.php

require_once __DIR__ . '/View.php';

/**
 * MenuView – loads all data needed by Frontend/ADMIN/menu-management.php.
 *
 * Usage at the top of menu-management.php:
 *   require_once '../../Frontend/Core/MenuView.php';
 *   $view = new MenuView();
 */
class MenuView extends View
{
    public int   $total;
    public int   $active;
    public int   $inactive;
    public int   $cats;

    public array $items              = [];
    public array $ingredientsList    = [];
    public array $menuIngredientsMap = [];

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->load();
    }

    private function load(): void
    {
        // ── Stats ────────────────────────────────────────────────
        $this->total    = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM menu")['c']                             ?? 0);
        $this->active   = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM menu WHERE is_available = 1")['c']     ?? 0);
        $this->inactive = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM menu WHERE is_available = 0")['c']     ?? 0);
        $this->cats     = (int) ($this->fetchOne("SELECT COUNT(DISTINCT category) AS c FROM menu")['c']            ?? 0);

        // ── All menu items ───────────────────────────────────────
        $this->items = $this->fetchAll('SELECT * FROM menu ORDER BY created_at DESC');

        // ── Ingredient dropdown list ─────────────────────────────
        $this->ingredientsList = $this->fetchAll(
            'SELECT id, name, unit FROM ingredients ORDER BY name ASC'
        );

        // ── menu_ingredients map keyed by menu_id ────────────────
        $rows = $this->fetchAll(
            'SELECT mi.menu_id, mi.ingredient_id, mi.qty_needed, i.name, i.unit
             FROM menu_ingredients mi
             JOIN ingredients i ON i.id = mi.ingredient_id
             ORDER BY mi.menu_id, i.name'
        );
        foreach ($rows as $row) {
            $this->menuIngredientsMap[$row['menu_id']][] = $row;
        }
    }
}
