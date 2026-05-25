# Frontend OOP Refactor – Migration Guide

## What changed

Every admin page and the POS page previously had a raw PHP block at the top
that mixed session checks, `require_once conn.php`, and inline DB queries
directly with HTML. These blocks are now replaced by a single `require_once`
of a dedicated **View class**, and the HTML body is left 100% intact.

---

## File-by-file mapping

| Original page | PHP header replaced with | View class |
|---|---|---|
| `ADMIN/index2.php` | `require_once DashboardView` | `Frontend/Core/DashboardView.php` |
| `ADMIN/staff-list.php` | `require_once StaffView` | `Frontend/Core/StaffView.php` |
| `ADMIN/inventory.php` | `require_once InventoryView` | `Frontend/Core/InventoryView.php` |
| `ADMIN/menu-management.php` | `require_once MenuView` | `Frontend/Core/MenuView.php` |
| `ADMIN/suppliers.php` | `require_once SupplierView` | `Frontend/Core/SupplierView.php` |
| `ADMIN/void_refund.php` | `require_once VoidRefundView` | `Frontend/Core/VoidRefundView.php` |
| `ADMIN/sale_revenue.php` | `require_once SalesRevenueView` | `Frontend/Core/SalesRevenueView.php` |
| `ADMIN/report.php` | `require_once ReportView` | `Frontend/Core/ReportView.php` |
| `ADMIN/settings.php` | `require_once SettingsView` | `Frontend/Core/SettingsView.php` |
| `POS.php` | `require_once PosView` | `Frontend/Core/PosView.php` |

---

## Form action URL changes

All HTML `<form action="...">` attributes and JS `fetch(...)` calls now point
to the new Controllers instead of the old flat scripts:

| Old path | New path |
|---|---|
| `Backend/process.php` | `Backend/Controllers/StaffController.php` |
| `Backend/inventory_process.php` | `Backend/Controllers/InventoryController.php` |
| `Backend/menu_process.php` | `Backend/Controllers/MenuController.php` |
| `Backend/supplier_process.php` | `Backend/Controllers/SupplierController.php` |
| `Backend/pos_process.php` | `Backend/Controllers/PosController.php` |
| `Backend/pos_void_refund.php` | `Backend/Controllers/PosController.php` |
| `Backend/pos_get_order_items.php` | `Backend/Controllers/OrderItemsController.php` |

---

## How the View classes work

Each View class:

1. **Calls `Auth::requireAdmin()` or `Auth::requireStaff()`** — replaces the
   raw `session_name / session_start / if (!isset($_SESSION...))` block.
2. **Dispatches AJAX / SSE requests early** (before any HTML is sent) if a
   recognised query-string parameter is present (e.g. `?sse=1`, `?rt=1`,
   `?ajax=topitems`).
3. **Runs all DB queries** via the singleton `Database::getInstance()`.
4. **Exposes named public properties** that the page template reads through
   thin `$alias = $view->property` assignments at the very top of each page.

---

## Variables that map to the HTML

Because the HTML in every page references plain PHP variables
(e.g. `$items`, `$totalRevenue`, `$chartDataJson`), each refactored page
keeps short alias assignments immediately after instantiating the View.
The HTML itself requires **no changes at all**.

---

## Complete directory layout after refactor

```
Frontend/
├── Core/                   ← NEW  (all data/logic lives here)
│   ├── View.php            base class (auth, DB, flash messages)
│   ├── DashboardView.php
│   ├── InventoryView.php
│   ├── MenuView.php
│   ├── PosView.php
│   ├── ReportView.php
│   ├── SalesRevenueView.php
│   ├── SettingsView.php
│   ├── StaffView.php
│   ├── SupplierView.php
│   └── VoidRefundView.php
│
├── ADMIN/                  ← PHP header only, HTML unchanged
│   ├── index2.php
│   ├── staff-list.php
│   ├── inventory.php
│   ├── menu-management.php
│   ├── suppliers.php
│   ├── void_refund.php
│   ├── sale_revenue.php
│   ├── report.php
│   └── settings.php
│
└── POS.php                 ← PHP header only, HTML unchanged
```
