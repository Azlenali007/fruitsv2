# N.A Fresh Fruits & Coconuts — Store & Admin Portal

Production-oriented PHP 8.2+ and MySQL web application for **N.A Fresh Fruits & Coconuts**, built to match the approved brand visual specifications.

---

## 🌴 System Features

### Customer Storefront
- **Reference-Accurate Visual Design**: Pixel-matched emerald palette (`#235d3a`), typography, badge hierarchy, spacing, stone-slab hero visuals, and card layouts.
- **Fresh Tender Coconut Water**: Single-product focus with live prices (`₹40 /pc`), real-time MySQL inventory status, stepper quantity selector, and live subtotal calculator.
- **Shopping Cart**: Real-time server-side stock validation, session persistence, line total recalculation, and empty-state handling.
- **Local Delivery & Shop Pickup Checkout**: Full Indian mobile number validation, full address inputs with inline editor, and Cash on Delivery / Online Payment selection.
- **Fixed Mobile Bottom Navigation**: Always-visible viewport-fixed bottom navigation (`Home`, `Shop`, `Cart` with live counter badge, `Profile`/`Track Orders`) with safe-area insets.
- **Order Tracking & Confirmation**: Displays unique order number (e.g., `#NA1024`), visual preparation/delivery timeline, estimated delivery times, and status logs.

### In-Browser Web Installer (`/install`)
- **Step 1 — System Requirements**: Real-time checks for PHP 8.2+, PDO MySQL, Mbstring, cURL, GD image extension, and writable directories (`storage/`, `app/config/`, `public/assets/uploads/`).
- **Step 2 — Database Configuration**: Interactive PDO host, port, db name, and user credential testing with non-destructive connection verification.
- **Step 3 — Table Initialization**: Safe schema importer from `database/schema.sql` that avoids overwriting existing production records.
- **Step 4 — Initial Administrator Account**: Form to securely create the primary store manager with bcrypt password hashing.
- **Step 5 — Business Settings**: Configure store name, currency, starting coconut price, stock, delivery fees, and operating hours.
- **Step 6 — Final Validation & Security Lock**: Creates `storage/installed.lock` to permanently disable the installer and prevent re-initialization.

### Secure Store Admin Panel (`/admin`)
- **Protected Access**: Throttled login protection (5 attempts / 15 minutes), bcrypt password verification, CSRF token validation, session ID regeneration upon login, and secure cookies.
- **Operational Dashboard**: Live database metrics for Orders Today, Sales Today (excluding cancellations), Pending, Confirmed, Delivered, and Cancelled counts, plus a 7-day sales trend powered by Chart.js.
- **Order Management**: Search by order number, customer name, phone; filter by status and date range; review full historical item price snapshots; update statuses with audit trail comments.
- **Atomic Stock Restoration**: Automatic stock inventory replenishment upon order cancellation.
- **Product & Stock Management**: Update live price per piece, stock quantities, availability toggle, description, and product imagery.
- **Delivery & Business Settings**: Configure delivery charges, minimum order amount for delivery, free delivery threshold, and served delivery localities.
- **Financial & Sales Reports**: Filterable date-range revenue analytics, payment method breakdown (COD vs Online), and order status doughnut chart.

---

## 🔒 Strict File Policy & Final Audit

As mandated by Section 1.4:
- **.ts file count**: `0`
- **.tsx file count**: `0`
- Zero TypeScript build systems or frameworks; 100% PHP 8.2 backend, PDO prepared statements, Tailwind CSS, Alpine.js, and Chart.js.

Verification command:
```bash
find . -type f \( -iname "*.ts" -o -iname "*.tsx" \)
```

---

## 🛠️ Architecture & Project Directory

```
├── app/
│   ├── bootstrap.php          # Application bootstrap, CSRF, XSS escaper, sessions
│   ├── config/
│   │   ├── config.php         # App and MySQL configuration
│   │   └── database.php       # PDO connection singleton
│   ├── models/
│   │   ├── Admin.php          # Admin auth, login attempt throttling, password hashing
│   │   ├── Order.php          # Orders, order items snapshots, status history, analytics
│   │   ├── Product.php        # Product queries, stock updates, availability
│   │   └── Setting.php        # Key-value store and delivery configuration
│   ├── services/
│   │   ├── AuthService.php    # Session authentication service
│   │   ├── CartService.php    # Real-time cart calculations & validation
│   │   └── OrderService.php   # Checkout processing, transaction, stock deduction
│   └── views/
│       ├── layouts/
│       │   ├── header.php     # Brand header, desktop nav, search drawer
│       │   └── footer.php     # Desktop footer & fixed mobile bottom navigation
│       ├── home.php           # Desktop/mobile homepage matching reference image
│       ├── shop.php           # Product page matching top-right reference image
│       ├── cart.php           # Cart page matching bottom-left reference image
│       ├── checkout.php       # Checkout page matching bottom-middle reference image
│       ├── confirmation.php   # Confirmation screen matching bottom-right reference image
│       ├── track.php          # Order tracking & lookup view
│       └── admin/
│           ├── layout-header.php
│           ├── layout-footer.php
│           ├── login.php      # Admin authentication view
│           ├── dashboard.php  # Live metrics & Chart.js sales trend
│           ├── orders.php     # Filterable orders table
│           ├── order-detail.php # Order inspection & status management
│           ├── products.php   # Product price & inventory management
│           ├── settings.php   # Delivery & store configuration
│           └── reports.php    # Financial & sales analytics
├── database/
│   ├── schema.sql             # Complete normalized MySQL DDL schema
│   └── setup.php              # Automated schema installer and data seeder
├── public/
│   ├── index.php              # Front controller and router
│   └── assets/
│       └── images/            # Extracted product images and icons
├── storage/
│   ├── logs/                  # Application error logs
│   └── uploads/               # Product image uploads
├── tests/
│   └── run_tests.php          # 27-point automated verification test suite
├── package.json               # Dev server script configuration
├── router.php                 # PHP built-in web server router
├── run-dev.sh                 # Environment runner (MariaDB check + PHP server)
└── README.md
```

---

## 🚀 Setup & Installation Instructions

### Requirements
1. **PHP 8.2 or newer** with extensions:
   - `pdo_mysql`
   - `mbstring`
   - `gd`
   - `curl`
2. **MySQL 8.0+** or **MariaDB 10.5+**

### Database Setup
1. Create database and import schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS na_coconuts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p na_coconuts < database/schema.sql
   ```
2. Run the automated PHP seeder:
   ```bash
   php database/setup.php
   ```

### Default Admin Credentials
- **URL**: `http://localhost:3000/admin`
- **Username**: `admin`
- **Password**: `admin12345`

### Running the Application
```bash
php -S 0.0.0.0:3000 -t public router.php
```
Or with npm:
```bash
npm run dev
```

### Running Automated Verification Tests
```bash
php tests/run_tests.php
```
Test results:
- Database integrity: PASSED
- Cart calculation & price re-validation: PASSED
- Transactional order placement with stock deduction: PASSED
- Idempotency & duplicate submission block: PASSED
- Atomic stock restoration on order cancellation: PASSED
- Security XSS output escaping & CSRF token enforcement: PASSED
- Admin authentication & session security: PASSED
- All 27 automated tests pass with 0 failures.
