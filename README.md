# SMART STOCK — Digital Inventory Management System
### Botol Anggun Sdn. Bhd. — Demo 3 / Final (Final Year Project)

A real, working inventory management web application built with **PHP 8, MySQL, Bootstrap 5,
and vanilla JavaScript**, designed to run locally on **XAMPP**. This is not a static mock-up —
every screen reads from and writes to a real MySQL database.

> **Upgrading an existing install?** Do **not** re-import `sql/schema.sql` — that would wipe your
> data. Run the migrations you haven't run yet, in order:
> `sql/migration_demo2.sql` then `sql/migration_demo3.sql`. See *Setup* below.

## Modules

### Completed in Demo 1

- **Login** — session-based authentication, roles (`admin` / `staff`), passwords hashed with
  PHP's `password_hash()` / verified with `password_verify()`.
- **Product Management** — full CRUD (add, edit, delete, search) with image upload, separate
  cost price and selling price per unit (RM) with a live profit-margin hint, a "Last Updated"
  column, and **Print QR Labels** (`products/qr_print.php`) — printable QR labels for a single
  product, a search result, or the whole catalogue, in three sizes. Each label encodes the
  product's `qr_code` value (SKU by default), which is exactly what the scanner matches on.
- **Stock In** — search-first product picker → product details → date, quantity & remarks →
  save. Increases `current_stock` and logs the transaction with a staff-editable transaction
  date (defaults to today, can be backdated for late-entered deliveries, cannot be future-dated).
- **Stock Out** — same workflow, decreases `current_stock`, and blocks the transaction if the
  requested quantity exceeds what's available.

### New in Demo 2

- **QR Code Scanning** (`scan.php`) — uses the **browser camera** (jsQR) to read the QR codes
  Botol Anggun **already prints** on its products. SMART STOCK does *not* generate QR codes.
  One page serves both Stock In and Stock Out via a mode switch: scan → product is looked up in
  MySQL → image, name, category, supplier and current stock are shown → enter quantity and
  remarks → save. No mobile app required. A manual code-entry fallback is provided for when the
  camera is unavailable.
- **Low Stock Alerts** (`low_stock.php`) — a dedicated sidebar page (with a live count badge)
  listing every product below its minimum level, with product image, current vs minimum stock, a
  depletion bar, shortfall quantity, red warning badges and an estimated restock cost. Each row
  has a **Restock** button that opens Stock In with that product preselected. Matching alert
  cards also appear at the top of the Dashboard.
- **Reports** (`reports/`) — five reports (Inventory, Stock In, Stock Out, Low Stock, Category)
  with a date-range filter, and **Preview**, **Print / PDF** and **Export Excel** for each.
  Print/PDF opens a print-ready letterhead page and launches the print dialog (choose *Save as
  PDF*); Excel downloads a real `.xls` file. All rows and totals come from MySQL.
- **Real-Time Sales Monitoring** — a Dashboard widget showing Today's / Weekly / Monthly sales,
  a 7-day sales trend chart and Top Selling Products by revenue, all valued at each product's
  selling price.
- **Dashboard charts are all database-driven** — Monthly Stock In vs Stock Out, Inventory by
  Category, Best Selling Products, Monthly Inventory Movement and Top Restocked Products.
- **Entry method tracking** — every transaction records whether it was typed in manually or
  entered by QR scan, and this appears in the Stock In / Stock Out reports.

### New in Demo 3 (final)

- **User Management** (`users/`) — admin-only CRUD for accounts: add, edit, delete, reset
  password, and activate/deactivate. Roles are `admin` and `staff`. Guard rails stop you from
  deleting or deactivating your own account, or removing the last remaining administrator.
- **Activity Log** (`logs/activity.php`) — one page with two views, switched at the top:
  **Activity** (date, time, user, module, activity, product, quantity — what people did) and
  **Field Changes** (date, time, user, module, action, field, old value, new value — what values
  changed). Both are recorded automatically into separate tables (`activity_log` and `audit_log`)
  and share the same search, filters and pagination.
- **System Settings** (`settings.php`) — company profile with logo upload, language, per-type
  notification switches, session timeout, dark mode, and database **Backup** / **Restore**.
- **Notification Centre** (`notifications.php`) — real notifications for low stock, stock in,
  stock out, report generation and user changes, with a live unread badge in the top bar and
  sidebar, type filters and mark-all-as-read.
- **Security hardening** — idle session timeout, server-side role checks on every admin page,
  bcrypt password hashing, PDO prepared statements throughout, server-side input validation, and
  CSRF tokens on all state-changing forms.

The system is now feature-complete for Botol Anggun Sdn. Bhd.

## Requirements

- [XAMPP](https://www.apachefriends.org/) with **PHP 8.0+** and **MySQL/MariaDB**
- A modern browser
- Internet access on the machine running it (Bootstrap, Bootstrap Icons, Google Fonts and
  Chart.js are loaded from public CDNs — there is no local `node_modules`/build step to run)

## Setup (5 minutes)

1. **Copy the project folder.** Place the entire `smart-stock` folder inside your XAMPP
   `htdocs` directory, e.g.:
   - Windows: `C:\xampp\htdocs\smart-stock`
   - macOS: `/Applications/XAMPP/htdocs/smart-stock`
   - Linux: `/opt/lampp/htdocs/smart-stock`

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Import the database.** Open `http://localhost/phpmyadmin`, then follow **one** of these:

   **A — Fresh install (no existing `smart_stock` database):**
   - Click **Import** → **Choose File** → select **`sql/schema.sql`** → click **Go**
   - This creates the `smart_stock` database, all six tables, and seeds it with 2 demo
     accounts, 6 categories, 7 suppliers, 19 products and ~30 sample transactions.
   - Then select the `smart_stock` database → **Import** → **`sql/migration_demo2.sql`** → **Go**
   - Then **Import** → **`sql/migration_demo3.sql`** → **Go** to add the Demo 3 tables.

   **B — Upgrading an existing database (keeps all your data):**
   - Select the existing **`smart_stock`** database in the left sidebar
   - Click **Import** → run each migration you have not run yet, in order:
     **`sql/migration_demo2.sql`**, then **`sql/migration_demo3.sql`**
   - ⚠️ Do **not** re-import `schema.sql` — it drops and recreates the database, which would
     delete every product and transaction you have entered.

   The Demo 2 migration only *adds* things: a `qr_code` column on products (seeded from each
   product's SKU), an `entry_method` column on both transaction tables, and two indexes. No
   existing table, column, row or relationship is removed or altered.

4. **Open the app.** Visit `http://localhost/smart-stock/` in your browser. You'll be
   redirected to the login page.

5. **Log in** with either demo account:

   | Role  | Username     | Password   |
   |-------|--------------|------------|
   | Admin | `nish_admin` | `admin123` |
   | Staff | `adam_staff` | `staff123` |

That's it — every module is fully functional against the real database from this point on.

## Using the QR scanner

Browsers only allow camera access on a **secure origin**. In practice:

So for the demo, run it on the same machine via `localhost`. If you need to demo from a phone on
the same Wi-Fi, either set up HTTPS, or use the **"Type the code printed under the QR"** link on
the scan page, which does the same MySQL lookup without the camera.

To try scanning without printed labels, generate a test QR code from any free online generator
using a product's SKU as the content (e.g. `BTL-PET-500`), display it on screen, and scan it.
The `qr_code` column stores what each product's printed label encodes — the migration seeds it
from the SKU, and the warehouse team would update it to whatever their real labels contain.

## If your MySQL setup isn't the XAMPP default

`config/database.php` assumes the standard XAMPP defaults (`root` user, no password, host
`localhost`). If your MySQL is configured differently, edit the four constants at the top of
that file:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_stock');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## Project structure

```
smart-stock/
├── auth/
│   ├── login.php          Login form + credential check (POST self-submits here)
│   └── logout.php         Destroys the session
├── api/
│   ├── search_products.php  JSON endpoint powering the Stock In/Out searchable picker
│   └── lookup_qr.php        JSON endpoint resolving a scanned QR payload to a product
├── assets/
│   ├── css/style.css       The whole blue & white ERP design system
│   ├── js/app.js           Sidebar toggle (mobile)
│   ├── js/charts.js        Dashboard Chart.js setup (all 6 charts)
│   ├── js/products.js      Add/Edit modal + image upload preview + margin hint
│   ├── js/stock.js         Shared Stock In / Stock Out search + form logic
│   ├── js/scan.js          Camera QR scanning (jsQR) + product lookup
│   ├── js/monitoring.js    Inventory Monitoring charts
│   └── img/placeholder-product.svg
├── config/
│   ├── bootstrap.php       Session start + base URL detection, include this first
│   └── database.php        PDO connection (edit credentials here if needed)
├── includes/
│   ├── functions.php       Helpers: e(), base_url(), require_login(), stock_status(), ...
│   ├── header.php          Sidebar + topbar + notification bell (shared layout)
│   └── footer.php          Closing markup + shared scripts
├── products/
│   ├── index.php           Product list, search, Add/Edit modal
│   ├── save.php            Insert/update handler (validates + handles image upload)
│   ├── delete.php          Delete handler
│   └── qr_print.php        Printable QR labels (single / search / all products)
├── reports/
│   ├── index.php           Report gallery + date-range filter
│   └── generate.php        Builds all 5 reports in HTML / print-PDF / Excel
├── uploads/products/        Uploaded product images land here
├── users/
│   ├── index.php           User list + add/edit/reset-password modals
│   └── save.php            Add / edit / delete / reset / activate handler
├── logs/
│   └── activity.php        Activity Log — Activity + Field Changes views
├── sql/
│   ├── schema.sql           Full schema + seed data (fresh installs only)
│   ├── migration_demo2.sql  Demo 1 → Demo 2 upgrade (safe, additive)
│   └── migration_demo3.sql  Demo 2 → Demo 3 upgrade (safe, additive)
├── index.php                 Redirects to dashboard or login
├── dashboard.php             KPIs, sales monitoring, alerts, 6 live charts
├── scan.php                  QR scanning for Stock In & Stock Out
├── low_stock.php             Low Stock Alerts page
├── notifications.php         Notification centre
├── settings.php / settings_save.php   System Settings
├── backup.php                Downloads a full .sql database backup
├── restore.php               Restores the database from an uploaded backup
├── stock_in.php / stock_in_save.php
├── stock_out.php / stock_out_save.php
└── README.md                 You are here
```

## Database design

| Table        | Purpose                                                             |
|--------------|----------------------------------------------------------------------|
| `users`      | Login accounts. `role` is `admin` or `staff`.                       |
| `categories` | Product categories (PET Bottles, Glass Bottles, etc.)               |
| `suppliers`  | Supplier directory.                                                  |
| `products`   | The catalogue. FKs to `categories` and `suppliers` (`ON DELETE SET NULL`, so removing a category/supplier never deletes a product). Demo 2 adds `qr_code` (unique) — the value encoded by the label already printed on the product. |
| `stock_in`   | One row per stock-in transaction. Has its own `transaction_date` (the date stock was actually received, editable by staff) separate from `created_at` (when the record was entered). Demo 2 adds `entry_method` (`manual` / `qr`). FK to `products` (`ON DELETE RESTRICT` — a product with transaction history can't be deleted) and `users`. |
| `stock_out`  | Same shape as `stock_in`, for outgoing stock.                        |
| `activity_log` | Demo 3. One row per business action (who, module, activity, product, quantity). User name and product name are denormalised so history survives a deletion. |
| `audit_log`  | Demo 3. One row per changed field (module, action, field, old value, new value). |
| `notifications` | Demo 3. Notification centre. `user_id` NULL means a broadcast everyone sees. |
| `settings`   | Demo 3. Simple key/value store for company profile, notification switches, dark mode, session timeout. |

`products.current_stock` is the single source of truth for "how much is in the warehouse right
now." It is updated atomically (inside a DB transaction, with a row lock) every time a Stock In
or Stock Out is saved — that's also exactly what keeps the Dashboard's numbers current, since
the Dashboard simply queries `products` and `stock_in`/`stock_out` live on every page load.

Demo 2 **extends** this schema rather than replacing it: no table was dropped, no column
removed, and every existing foreign key and relationship is untouched.

## Notes for the demo / viva

- Passwords are hashed with bcrypt (`password_hash()` / `password_verify()`), not stored in
  plain text.
- All database queries use PDO **prepared statements** — no raw string concatenation of user
  input into SQL, anywhere.
- Stock Out uses `SELECT ... FOR UPDATE` inside a transaction before deducting stock, so two
  people saving a Stock Out for the same product at the same instant can't oversell it.
- Uploaded images are validated by actual file content (`finfo`), not just their extension, and
  stored under a randomised filename.
- The `uploads/` folder has a `.htaccess` rule blocking PHP execution, so an uploaded file
  can never be run as a script even if someone tried to disguise one as an image.
- **QR scanning reads, never writes labels.** The system decodes existing printed codes with
  jsQR in the browser; it has no QR *generation* code anywhere, matching how Botol Anggun
  already labels its products.
- The QR endpoint also accepts labels that encode a **URL** (e.g.
  `https://botolanggun.com/p/BTL-PET-500`) by taking the last path segment, so both bare-code
  and URL-style labels work.
- The `redirect_to` field on the scan form is **whitelisted server-side**, so it cannot be
  abused as an open redirect.
- **Every figure on every screen is a live MySQL query.** There is no hard-coded sample data in
  any chart, report or KPI — deleting all transactions would correctly show zeros everywhere.
- Excel export is produced as an Excel-readable HTML table with `.xls` headers, so it needs **no
  external PHP library** — it works on a stock XAMPP install with nothing extra to install.
