# 🍽️ Tracky — Multi-Restaurant Food Delivery & Order Tracking

Tracky is a **multi-tenant food delivery web application** where multiple restaurants operate on a single platform. Customers browse restaurants, place orders, and track delivery in real time, while each restaurant manages its own menu, orders, and runners — all isolated by tenant. A **System Owner (Super Admin)** oversees the entire platform.

Built with plain **PHP + MySQL** (no framework), it runs out of the box on **XAMPP**.

---

## ✨ Key Features

- **Multi-restaurant (multi-tenant):** every restaurant has its own menu, orders, runners, and notifications, fully isolated by `restaurant_id`.
- **Customer ordering flow:** restaurant directory → menu → cart → checkout → order success → live tracking. No account required.
- **Per-restaurant branding:** logo, cover image, accent colour, delivery fee, and free-delivery threshold.
- **Role-based access control (RBAC):** four distinct dashboards with strict page- and API-level authorization.
- **Order tracking:** customers track an order by its order number; status updates flow from kitchen to runner to delivery.
- **Runner workflow:** runners accept, pick up, and complete deliveries with status transitions.
- **Image-rich UI:** restaurant covers and menu item photos throughout, with cart thumbnails.
- **Light / dark mode** and a responsive, modern interface (Bootstrap 5 + Tabler Icons).

---

## 👥 User Roles

| Role | Description | Home |
| --- | --- | --- |
| **System Owner** (Super Admin) | Full control of the whole platform: manage all stores, all users, reset passwords, and view aggregate stats. | `superadmin/superadmin_dashboard.php` |
| **Admin** | Manages a single restaurant: menu, orders, runners, assignments, reports, and notifications. | `admin/admin_dashboard.php` |
| **Staff** | Restricted interface for a single restaurant: Dashboard, Notifications, and Account only. | `staff/staff_dashboard.php` |
| **Runner** | Delivery rider: views assigned orders, updates delivery status, and sees history. | `runner/runner_orders.php` |
| **Customer** | No login required. Browses, orders, and tracks deliveries. | `customer/customer_restaurants.php` |

---

## 🛠️ Tech Stack

- **Backend:** PHP 8 (procedural, `mysqli`)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS, vanilla JavaScript (Fetch API)
- **UI:** Bootstrap 5, Tabler Icons, Inter font
- **Server:** Apache (XAMPP)

---

## 📁 Project Structure

```
tracky/
├── index.php                 # Entry point → landing page / role redirect
├── login.php                 # Shared login for all roles
├── landing/                  # Public marketing landing page
├── customer/                 # Customer pages (restaurants, menu, cart, track)
├── admin/                    # Restaurant admin panel
├── staff/                    # Restricted staff panel
├── superadmin/               # System Owner panel
├── runner/                   # Runner (delivery) panel
├── api/                      # JSON endpoints (admin_*, customer_*, runner_*, staff_*)
├── includes/                 # db.php, functions.php, auth guards, shared layouts
├── assets/
│   ├── css/                  # admin.css, customer.css, runner.css
│   ├── js/                   # admin.js, customer.js, staff.js, theme.js
│   ├── img/                  # logos & icons
│   └── uploads/              # restaurant covers & menu item images
└── database/
    ├── tracky.sql            # Schema + seed data (create this first)
    ├── migrate_multitenant.php
    └── seed_restaurant2.php
```

---

## 🚀 Getting Started (XAMPP)

> The app uses absolute paths under `/tracky/`, so the project folder **must** be named `tracky` inside `htdocs`.

1. **Install [XAMPP](https://www.apachefriends.org/)** and start **Apache** and **MySQL**.

2. **Clone into `htdocs`:**
   ```bash
   cd C:/xampp/htdocs
   git clone https://github.com/irfankhasim/tracky.git
   ```

3. **Import the database.** Open [phpMyAdmin](http://localhost/phpmyadmin) → **Import** → choose `database/tracky.sql` → **Go**.  
   This creates the `tracky_db` database with all tables, demo restaurants, and seed accounts.

   *(Alternatively via CLI:)*
   ```bash
   mysql -u root < database/tracky.sql
   ```

4. **Check the DB connection** in `includes/db.php` (defaults match a fresh XAMPP install):
   ```php
   $conn = mysqli_connect('localhost', 'root', '', 'tracky_db');
   ```

5. **Open the app:** [http://localhost/tracky/](http://localhost/tracky/)

---

## 🔑 Demo Accounts

All accounts are seeded by `database/tracky.sql`.

| Role | Email | Password |
| --- | --- | --- |
| System Owner | `superadmin@tracky.com` | `superadmin123` |
| Admin (Restoran Selera) | `admin@tracky.com` | `admin123` |
| Admin (Warung Pak Abu) | `admin2@tracky.com` | `admin123` |
| Staff (Restoran Selera) | `staffselera@tracky.com` | `staff123` |
| Staff (Warung Pak Abu) | `staffpakabu@tracky.com` | `staff123` |
| Runner | `runner1@tracky.com` | `runner123` |

Customers don't log in — they order directly from the [restaurant directory](http://localhost/tracky/customer/customer_restaurants.php).

---

## 🧭 How It Works

- **Tenant isolation:** every domain table carries a `restaurant_id`. Queries are always scoped to the active restaurant, and the System Owner can switch context to act on any store.
- **Auth guards:** `includes/admin_auth.php`, `staff_auth.php`, `superadmin_auth.php`, and `runner_auth.php` protect each panel; API endpoints enforce the same rules so direct URL access is blocked.
- **Customer session:** the chosen restaurant and cart are kept in the session, so switching restaurants starts a fresh cart.

---

## 📝 Notes

- This is an educational / portfolio project. For production use, add HTTPS, CSRF protection, prepared-statement coverage review, stronger input validation, and environment-based DB configuration.
- Menu and restaurant images in `assets/uploads/` are sourced from openly available food photography for demo purposes.

---

## 📄 License

No license has been specified yet. Until one is added, all rights are reserved by the author. If you intend to reuse this project, please open an issue or contact the repository owner.
