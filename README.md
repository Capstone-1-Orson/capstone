# Empress Café – OOP Refactored Backend

## What changed

The original `Backend/` folder contained seven procedural scripts that mixed
routing, validation, database calls, and email sending in a single file each.
This refactor restructures them into a layered OOP architecture with no
behaviour changes.

---

## Directory layout

```
Backend/
├── Core/
│   ├── Database.php      – Singleton MySQLi wrapper
│   ├── Session.php       – Named-session helper + flash messages
│   └── Auth.php          – Role guards + CSRF verification
│
├── Models/               – Pure DB operations (no HTTP/session logic)
│   ├── User.php
│   ├── Ingredient.php
│   ├── Menu.php
│   ├── Supplier.php
│   └── Order.php
│
├── Services/             – Business logic that spans multiple models
│   ├── MailerService.php – All outbound email (OTP + verification)
│   ├── ImageUploadService.php
│   └── OrderService.php  – Stock pre-check, place, void/refund
│
└── Controllers/          – HTTP entry points (one per original script)
    ├── LoginController.php      ← login.php
    ├── StaffController.php      ← process.php
    ├── InventoryController.php  ← inventory_process.php
    ├── MenuController.php       ← menu_process.php
    ├── SupplierController.php   ← supplier_process.php
    └── PosController.php        ← pos_process.php + pos_void_refund.php
```

---

## OOP principles applied

| Principle | Where |
|-----------|-------|
| **Single Responsibility** | Every class owns exactly one concern. `MailerService` only sends email; `User` only talks to the `user` table. |
| **Encapsulation** | DB credentials live inside `Database`; SMTP credentials inside `MailerService`. No raw globals leak out. |
| **Singleton** | `Database::getInstance()` ensures one connection per request. |
| **Dependency Injection** | Controllers accept model/service objects they own, making unit testing straightforward. |
| **DRY** | Image upload, password/email/contact validation, OTP generation, and redirect helpers are each written once. |
| **Open/Closed** | Adding a new upload type (e.g. `'logo'`) only requires adding one constant to `ImageUploadService` — existing callers don't change. |

---

## Migration: file-by-file mapping

| Old file | New controller | Notes |
|----------|----------------|-------|
| `conn.php` | `Core/Database.php` | Singleton; no global `$conn` |
| `login.php` | `Controllers/LoginController.php` | |
| `process.php` (staff CRUD) | `Controllers/StaffController.php` | |
| `inventory_process.php` | `Controllers/InventoryController.php` | |
| `menu_process.php` | `Controllers/MenuController.php` | |
| `supplier_process.php` | `Controllers/SupplierController.php` | |
| `pos_process.php` | `Controllers/PosController.php` | merged |
| `pos_void_refund.php` | `Controllers/PosController.php` | merged |

---

## How to swap in the refactored files

1. Copy the new `Backend/` sub-folders alongside the existing ones.
2. Update `<form action="...">` in the frontend PHP pages to point to the new
   controller paths (e.g. `../../Backend/Controllers/StaffController.php`).
3. The database schema (`empress_cafe.sql`) is **unchanged**.
