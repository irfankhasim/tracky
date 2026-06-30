# 📘 Dokumentasi Sistem Tracky

> **Dokumentasi Teknikal Rasmi**
> Sistem Penghantaran Makanan & Penjejakan Pesanan Pelbagai Restoran (Multi-Tenant)
>
> Dokumen ini ditulis dari perspektif **pembangun utama (lead developer)** sistem. Ia menerangkan keseluruhan sistem dari hujung ke hujung — frontend, backend, dan pangkalan data — supaya seseorang yang tidak pernah melihat projek ini boleh memahami sepenuhnya hanya dengan membaca dokumen ini.

> **ℹ️ Nota kejujuran teknikal:** Dokumen ini membezakan dengan jelas antara **ciri yang telah dilaksanakan** dalam kod sebenar dan **ciri yang dicadangkan** (belum dibina). Bahagian yang dicadangkan ditanda dengan label _(Cadangan / Future)_ atau diletakkan dalam Seksyen 14. Ini penting supaya dokumentasi kekal tepat dan boleh dipercayai.

---

## Isi Kandungan

1. [System Overview](#1-system-overview)
2. [User Roles](#2-user-roles)
3. [Authentication System](#3-authentication-system)
4. [Database Architecture](#4-database-architecture)
5. [Module Explanation](#5-module-explanation)
6. [Order Workflow](#6-order-workflow)
7. [Notification System](#7-notification-system)
8. [Google Maps Integration](#8-google-maps-integration)
9. [System Security](#9-system-security)
10. [UI/UX Concept](#10-uiux-concept)
11. [Folder Structure](#11-folder-structure)
12. [Backend Flow](#12-backend-flow)
13. [API & AJAX Flow](#13-api--ajax-flow)
14. [Future Improvements](#14-future-improvements)

---

## 1. System Overview

### 1.1 Nama Sistem
**Tracky** — *Multi-Restaurant Food Delivery & Order Tracking System*.

### 1.2 Objektif Sistem
Tracky ialah satu platform **multi-tenant** (pelbagai penyewa) yang membolehkan **beberapa restoran beroperasi serentak di atas satu sistem yang sama**. Setiap restoran mempunyai menu, pesanan, runner, dan notifikasi tersendiri yang **terasing sepenuhnya** daripada restoran lain, manakala seorang **Sistem Owner (Super Admin)** mengawasi keseluruhan platform.

Objektif utama:
- Menyediakan saluran pesanan dalam talian untuk pelanggan tanpa perlu mendaftar akaun.
- Membenarkan setiap restoran menguruskan operasi hariannya (menu, pesanan, runner) secara berasingan.
- Menjejak status penghantaran dari dapur sehingga sampai ke pintu pelanggan.
- Memberi pemilik platform (Super Admin) kawalan penuh ke atas semua kedai dan pengguna.

### 1.3 Masalah yang Diselesaikan
- **Pengasingan data antara restoran:** Kebanyakan sistem ringkas hanya menyokong satu kedai. Tracky menyelesaikannya dengan seni bina multi-tenant berasaskan lajur `restaurant_id` pada setiap jadual penting.
- **Penjejakan pesanan yang telus:** Pelanggan boleh menyemak status pesanan mereka secara berterusan menggunakan nombor pesanan (`order_no`).
- **Kawalan capaian mengikut peranan:** Setiap jenis pengguna (Admin, Staff, Runner, Super Admin) hanya nampak dan boleh buat apa yang dibenarkan untuk peranannya.
- **Operasi penghantaran berstruktur:** Aliran status pesanan yang jelas (pending → assigned → picked_up → in_transit → delivered) mengelakkan kekeliruan tentang di mana sesuatu pesanan berada.

### 1.4 Sasaran Pengguna
- **Pelanggan (Customer)** — orang awam yang ingin memesan makanan (tanpa akaun).
- **Pemilik/Pengurus Restoran (Admin)** — menguruskan satu restoran.
- **Kakitangan Restoran (Staff)** — memantau notifikasi & dashboard restoran (capaian terhad).
- **Penghantar (Runner)** — menghantar pesanan kepada pelanggan.
- **Pemilik Platform (Super Admin / Sistem Owner)** — mengurus seluruh sistem dan semua restoran.

### 1.5 Teknologi yang Digunakan

| Lapisan | Teknologi | Kegunaan |
| --- | --- | --- |
| Bahasa Server | **PHP 8** (gaya prosedural, `mysqli`) | Logik backend, pemprosesan borang, API |
| Pangkalan Data | **MySQL / MariaDB** | Penyimpanan data berstruktur |
| Markup & Struktur | **HTML5** | Struktur halaman |
| Gaya | **CSS3** + **Bootstrap 5** | Reka bentuk responsif & komponen UI |
| Interaktiviti | **JavaScript (vanilla)** + **Fetch API (AJAX)** | Kemas kini dinamik tanpa muat semula halaman |
| Ikon & Font | **Tabler Icons**, **Google Fonts (Inter)** | Elemen visual konsisten |
| Peta | **Google Maps (embed)** | Paparan lokasi & navigasi (lihat Seksyen 8) |
| Pelayan | **Apache (XAMPP)** | Hosting tempatan |

> **ℹ️ Nota:** Sistem ini **tidak** menggunakan framework PHP (seperti Laravel), Composer, atau ORM. Ia ditulis secara prosedural dengan pertanyaan `mysqli` yang disediakan (*prepared statements*). Google Maps digunakan melalui *embed iframe* dan bukan Google Maps JavaScript/Directions API (lihat Seksyen 8 & 14).

### 1.6 Seni Bina Sistem (Client–Server Architecture)

Tracky mengikut model **Client–Server** klasik dengan tiga lapisan logik:

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT (Pelayar)                       │
│   HTML + CSS (Bootstrap) + JavaScript (Fetch/AJAX)            │
│   - Memaparkan UI                                             │
│   - Menghantar permintaan (borang / fetch JSON)              │
└───────────────────────────┬─────────────────────────────────┘
                            │  HTTP (GET/POST)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   SERVER (Apache + PHP)                       │
│   - Halaman PHP (admin/, customer/, runner/, staff/, ...)    │
│   - Endpoint API (api/*.php) memulangkan JSON                │
│   - includes/ : auth guard, fungsi bantuan, susun atur (layout)│
│   - Pengesahan input, logik perniagaan, kawalan capaian      │
└───────────────────────────┬─────────────────────────────────┘
                            │  mysqli (prepared statements)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   DATABASE (MySQL: tracky_db)                 │
│   restaurants, users, runners, categories, menu_items,       │
│   orders, order_items, deliveries, status_logs, notifications│
└─────────────────────────────────────────────────────────────┘
```

- **Pelayar (Client)** tidak pernah menyentuh pangkalan data secara langsung. Ia hanya berkomunikasi melalui HTTP dengan PHP.
- **PHP (Server)** ialah satu-satunya lapisan yang menulis/membaca pangkalan data, melaksanakan pengesahan, dan menguatkuasakan keizinan.
- **MySQL (Database)** menyimpan semua data secara kekal.

---

## 2. User Roles

Sistem mempunyai **5 peranan**: Customer, Admin, Staff, Runner, dan Super Admin. Empat peranan terakhir log masuk melalui `login.php`; pelanggan tidak perlu log masuk.

### 2.1 Customer (Pelanggan)

| Perkara | Penerangan |
| --- | --- |
| **Tanggungjawab** | Melayari restoran, memilih menu, membuat pesanan, dan menjejak penghantaran. |
| **Boleh akses** | Direktori restoran, menu, troli, checkout, halaman kejayaan pesanan, dan penjejakan pesanan melalui `order_no`. |
| **Tidak boleh akses** | Sebarang panel pengurusan (admin/staff/runner/superadmin), data restoran lain, atau pesanan pelanggan lain. |
| **Workflow harian** | Buka laman → pilih restoran → tambah item ke troli → checkout (isi nama, telefon, alamat, kaedah bayaran) → terima `order_no` → jejak status. |
| **Hubungan dengan role lain** | Pesanan pelanggan mencetuskan notifikasi kepada restoran (Admin/Staff), kemudian di-assign kepada Runner. |

> **ℹ️ Nota implementasi:** Pelanggan ialah **tetamu (guest)** — **tiada akaun, tiada pendaftaran, tiada log masuk**. Konteks restoran & troli disimpan dalam *session*. Oleh itu ciri seperti "Order History peribadi", "Profile pelanggan", dan "Review" **belum wujud** (lihat Seksyen 14).

### 2.2 Admin (Pengurus Restoran)

| Perkara | Penerangan |
| --- | --- |
| **Tanggungjawab** | Menguruskan **satu restoran** sepenuhnya. |
| **Boleh akses** | Dashboard, pengurusan menu & kategori, pesanan, assign runner, pengurusan runner, laporan, notifikasi, profil. |
| **Tidak boleh akses** | Data restoran lain (semua pertanyaan ditapis `restaurant_id`), panel Super Admin, dan pengurusan pengguna global. |
| **Workflow harian** | Semak pesanan baru → assign runner kepada pesanan `pending` → pantau status → urus menu/stok ketersediaan → semak laporan. |
| **Hubungan dengan role lain** | Menerima pesanan daripada Customer; meng-assign Runner; menyelia Staff & Runner restorannya. |

### 2.3 Staff (Kakitangan)

| Perkara | Penerangan |
| --- | --- |
| **Tanggungjawab** | Memantau aktiviti restoran (paras capaian terhad). |
| **Boleh akses** | **Hanya** Dashboard, Notifikasi, dan Akaun (profil). |
| **Tidak boleh akses** | Semua ciri pengurusan (menu, pesanan, runner, laporan) — disekat di UI **dan** melalui URL terus (RBAC), serta API pengurusan. |
| **Workflow harian** | Log masuk → lihat dashboard ringkas → baca notifikasi restoran → kemas kini profil sendiri. |
| **Hubungan dengan role lain** | Berkongsi data restoran yang sama dengan Admin, tetapi tanpa kuasa mengurus. |

> **ℹ️ Nota implementasi:** Dalam kod sebenar, Staff ialah antara muka **baca sahaja (read-only)** untuk notifikasi & dashboard. Tindakan operasi seperti "Accept Order", "Prepare Food", "Update Status" yang disebut dalam keperluan ialah **konsep aliran kerja**; pada masa ini tindakan tersebut **tidak dilaksanakan** untuk Staff (assign/kemas kini status dibuat oleh Admin & Runner). Ini calon penambahbaikan (Seksyen 14).

### 2.4 Runner (Penghantar)

| Perkara | Penerangan |
| --- | --- |
| **Tanggungjawab** | Mengambil pesanan dari restoran dan menghantar kepada pelanggan. |
| **Boleh akses** | Senarai pesanan yang di-assign kepadanya, butiran pesanan, butang navigasi (Google Maps), kemas kini status penghantaran, sejarah penghantaran, profil, dan togol status online/offline. |
| **Tidak boleh akses** | Pesanan runner lain, panel admin/staff/superadmin, dan data restoran lain. |
| **Workflow harian** | Set status **online** → terima pesanan yang di-assign → "Saya Dah Ambil" (picked_up) → "Dalam Perjalanan" (in_transit) → navigasi ke alamat → "Dah Dihantar" (delivered). |
| **Hubungan dengan role lain** | Di-assign oleh Admin; mengemas kini status yang dilihat oleh Customer secara hampir masa nyata. |

### 2.5 Super Admin (Sistem Owner)

| Perkara | Penerangan |
| --- | --- |
| **Tanggungjawab** | Mengawal **keseluruhan platform** tanpa sekatan. |
| **Boleh akses** | Pengurusan semua kedai (CRUD restoran + penjenamaan), pengurusan semua pengguna (cipta/edit/reset password), dashboard agregat merentas semua restoran, dan "bertukar konteks" untuk bertindak sebagai mana-mana restoran. |
| **Tidak boleh akses** | (Tiada sekatan dalam sistem; ini peranan tertinggi.) |
| **Workflow harian** | Pantau statistik global → tambah/kemas kini restoran → urus akaun pengguna → reset kata laluan apabila perlu → masuk konteks restoran tertentu untuk semakan. |
| **Hubungan dengan role lain** | Mencipta & mengurus akaun Admin/Staff/Runner; menyelia semua restoran. |

> **ℹ️ Nota implementasi:** "Context switching" dilaksanakan melalui pemboleh ubah session `sa_acting_restaurant`. Apabila Super Admin memilih sesuatu restoran, fungsi `activeRestaurantId()` memulangkan id restoran tersebut, membolehkan Super Admin menggunakan panel admin seolah-olah dia admin restoran itu.

---

## 3. Authentication System

### 3.1 Login
- Fail: `login.php` (satu borang log masuk untuk **semua** peranan berakaun).
- Proses backend (ringkasan aliran):
  1. Borang dihantar secara **POST** (email + password).
  2. Pengesahan asas: kedua-dua medan mesti diisi.
  3. Pengguna dicari menggunakan **prepared statement**:
     `SELECT ... FROM users WHERE email = ? LIMIT 1`.
  4. Semakan berlapis:
     - Jika email tiada → ralat "E-mel tidak dijumpai".
     - Jika `is_active = 0` → ralat "Akaun digantung".
     - Jika `password_verify()` gagal → ralat "Kata laluan tidak betul".
     - Jika peranan `runner` tetapi tiada rekod dalam jadual `runners` → ralat "Akaun runner tidak lengkap".
  5. Jika lulus, data disimpan ke session: `user_id`, `name`, `email`, `role`, `phone`, `restaurant_id` (dan `runner_id`, `runner_status` untuk runner).
  6. Pengguna diarahkan ke dashboard mengikut peranan (lihat 3.5).

### 3.2 Register
> **ℹ️ Belum dilaksanakan.** Tiada `register.php`. Akaun **dicipta oleh Super Admin** melalui modul Pengurusan Pengguna, atau melalui data benih (`database/tracky.sql`). Pendaftaran kendiri pelanggan tidak diperlukan kerana pelanggan memesan sebagai tetamu. (Cadangan pendaftaran pelanggan: Seksyen 14.)

### 3.3 Forgot Password
> **ℹ️ Belum dilaksanakan sebagai aliran kendiri.** Tiada mekanisme "lupa kata laluan" melalui e-mel untuk pengguna. Sebaliknya, **Super Admin boleh reset kata laluan mana-mana pengguna** melalui `superadmin/superadmin_users.php` (tindakan `reset_password`). Kata laluan baharu di-*hash* semula menggunakan `password_hash()`. (Cadangan reset kendiri melalui e-mel: Seksyen 14.)

### 3.4 Session Management
- Setiap halaman yang dilindungi memanggil `session_start()`.
- Maklumat identiti pengguna disimpan dalam superglobal `$_SESSION`.
- Konteks penting yang disimpan dalam session:
  - `restaurant_id` — restoran yang dimiliki oleh pengguna (admin/staff/runner).
  - `sa_acting_restaurant` — restoran yang sedang "diwakili" oleh Super Admin.
  - `cust_restaurant_id` — restoran yang dipilih oleh pelanggan tetamu.
  - `cart` — kandungan troli pelanggan.
- **Logout** (`logout.php`) memusnahkan session dan mengembalikan pengguna ke `login.php`.

### 3.5 Role-based Authentication (Redirect mengikut peranan)
Selepas log masuk berjaya, pengguna diarah ke laman utama peranannya:

| Peranan | Halaman selepas log masuk |
| --- | --- |
| `superadmin` | `superadmin/superadmin_dashboard.php` |
| `admin` | `admin/admin_dashboard.php` |
| `staff` | `staff/staff_dashboard.php` |
| `runner` | `runner/runner_orders.php` |

### 3.6 Authorization (Kawalan Capaian / RBAC)
Keizinan dikuatkuasakan melalui **auth guard** yang di-`require` di bahagian atas setiap halaman terlindung:

| Guard | Melindungi | Logik |
| --- | --- | --- |
| `includes/admin_auth.php` | Halaman admin | Hanya `admin` & `superadmin`. Staff dialih ke panel staff; lain ke login. |
| `includes/staff_auth.php` | Halaman staff | Hanya `staff`. Peranan lain dialih ke panel masing-masing. |
| `includes/runner_auth.php` | Halaman runner | Hanya `runner`. |
| `includes/superadmin_auth.php` | Halaman superadmin | Hanya `superadmin`. |

Untuk **API**, fungsi penjaga dalam `includes/functions.php` memulangkan JSON `Unauthorized` jika peranan tidak layak:
- `requireAdminApi()` — `admin` atau `superadmin`.
- `requireStaffApi()` — `staff`.
- `requireRunnerApi()` — `runner` atau `superadmin`.

Ini memastikan capaian terus melalui URL (contoh: `/tracky/api/admin_get_orders.php`) **disekat** untuk peranan yang tidak dibenarkan.

### 3.7 Security yang Digunakan (ringkasan; lihat Seksyen 9 untuk perincian)
- Kata laluan di-*hash* menggunakan **bcrypt** (`password_hash` / `password_verify`).
- Semua pertanyaan SQL menggunakan **prepared statements** (`mysqli_prepare` + `bind_param`).
- Output pengguna di-*escape* dengan fungsi `e()` (`htmlspecialchars`) untuk mencegah XSS.
- Akaun yang `is_active = 0` tidak boleh log masuk.
- Multi-tenant: setiap pertanyaan ditapis `restaurant_id` supaya data restoran tidak bocor antara satu sama lain.

---

## 4. Database Architecture

Pangkalan data: **`tracky_db`** (dicipta oleh `database/tracky.sql`). Enjin: MySQL/MariaDB, set aksara `utf8mb4`.

### 4.1 Senarai Jadual & Fungsi

| Jadual | Fungsi Ringkas |
| --- | --- |
| `restaurants` | Maklumat & penjenamaan setiap restoran (tenant). |
| `users` | Akaun semua pengguna berperanan (superadmin/admin/staff/runner). |
| `runners` | Profil penghantar (dikaitkan dengan satu `users` & satu restoran). |
| `categories` | Kategori menu bagi setiap restoran. |
| `menu_items` | Item menu (makanan/minuman) bagi setiap restoran. |
| `orders` | Rekod pesanan pelanggan. |
| `order_items` | Baris item dalam setiap pesanan (snapshot nama & harga). |
| `deliveries` | Rekod tugasan penghantaran (order ↔ runner). |
| `status_logs` | Jejak audit setiap perubahan status pesanan. |
| `notifications` | Notifikasi peringkat restoran. |

### 4.2 Perincian Setiap Jadual

#### `restaurants`
- **Tujuan:** "Tenant" dalam seni bina multi-tenant. Setiap baris = satu restoran.
- **Primary Key:** `id`.
- **Lajur penting:** `name`, `slug` (unik), `address`, `phone`, `email`, `logo`, `cover_image`, `accent_color`, `delivery_fee`, `free_delivery_min`, `operating_hours`, `is_open`, `is_active`.
- **Foreign Key:** tiada (jadual induk).
- **Constraint:** `slug` UNIQUE; `accent_color` default `#1D9E75`; `is_active` default 1.
- **Mengapa diperlukan:** Tanpa jadual ini, sistem hanya boleh menyokong satu kedai. Ia menjadi titik rujukan `restaurant_id` untuk hampir semua jadual lain.

#### `users`
- **Tujuan:** Menyimpan akaun & kelayakan log masuk.
- **Primary Key:** `id`.
- **Foreign Key:** `restaurant_id → restaurants.id` (NULL untuk superadmin).
- **Lajur penting:** `name`, `email` (UNIQUE), `password` (hash bcrypt), `role` ENUM(`superadmin`,`admin`,`staff`,`runner`), `phone`, `is_active`.
- **Constraint:** `email` UNIQUE; indeks pada `restaurant_id`.
- **Flow data:** Dibaca semasa login; ditulis semasa Super Admin cipta/edit/reset pengguna.
- **Mengapa diperlukan:** Asas pengesahan & kebenaran (RBAC).

#### `runners`
- **Tujuan:** Maklumat tambahan khusus penghantar.
- **Primary Key:** `id`.
- **Foreign Key:** `user_id → users.id`, `restaurant_id → restaurants.id`.
- **Lajur penting:** `vehicle_no`, `phone`, `status` ENUM(`online`,`offline`,`busy`).
- **Flow data:** `status` berubah apabila di-assign (`busy`) dan selepas hantar (`online`/`busy`).
- **Mengapa diperlukan:** Memisahkan data operasi penghantaran daripada akaun log masuk; satu runner = satu pengguna.

#### `categories`
- **Tujuan:** Mengumpul item menu (cth: "Nasi & Lauk", "Minuman").
- **Primary Key:** `id`. **Foreign Key:** `restaurant_id → restaurants.id`.
- **Lajur penting:** `name`, `description`, `is_active`, `sort_order`.
- **Mengapa diperlukan:** Menyusun menu secara berstruktur dan membenarkan paparan menu yang teratur.

#### `menu_items`
- **Tujuan:** Item makanan/minuman yang boleh dipesan.
- **Primary Key:** `id`.
- **Foreign Key:** `category_id → categories.id`, `restaurant_id → restaurants.id`.
- **Lajur penting:** `name`, `description`, `image`, `price`, `is_available`.
- **Flow data:** Dibaca oleh modul menu pelanggan & admin; harga/nama disalin (snapshot) ke `order_items` semasa pesanan.
- **Mengapa diperlukan:** Sumber kebenaran untuk menu & harga.

#### `orders`
- **Tujuan:** Satu rekod bagi setiap pesanan pelanggan.
- **Primary Key:** `id`. **Foreign Key:** `restaurant_id → restaurants.id`.
- **Lajur penting:** `order_no` (UNIQUE, cth `ORD-2026-0042`), `customer_name`, `customer_phone`, `delivery_address`, `subtotal`, `delivery_fee`, `total_amount`, `payment_method` ENUM(`cash`,`online`), `payment_status`, `status` ENUM(`pending`,`assigned`,`picked_up`,`in_transit`,`delivered`,`cancelled`), `notes`.
- **Flow data:** Dicipta semasa checkout (status `pending`); `status` dikemas kini sepanjang aliran penghantaran.
- **Mengapa diperlukan:** Teras transaksi sistem; titik rujukan untuk penjejakan.

#### `order_items`
- **Tujuan:** Item-item dalam sesuatu pesanan.
- **Primary Key:** `id`. **Foreign Key:** `order_id → orders.id`.
- **Lajur penting:** `item_name`, `quantity`, `unit_price`, `subtotal`.
- **Reka bentuk penting:** Ia menyimpan **snapshot** `item_name` & `unit_price`, **bukan** FK ke `menu_items`. Sebabnya: jika harga/nama menu berubah kemudian, resit pesanan lama mesti kekal tepat.
- **Mengapa diperlukan:** Memelihara butiran sejarah pesanan secara kekal.

#### `deliveries`
- **Tujuan:** Mengaitkan satu pesanan dengan seorang runner & menjejak status penghantaran.
- **Primary Key:** `id`.
- **Foreign Key:** `order_id → orders.id`, `runner_id → runners.id`, `assigned_by → users.id`.
- **Lajur penting:** `assigned_at`, `picked_up_at`, `delivered_at`, `status` ENUM(`assigned`,`picked_up`,`in_transit`,`delivered`), `notes`.
- **Flow data:** Dicipta semasa Admin assign; cap masa direkod pada setiap peralihan.
- **Mengapa diperlukan:** Memisahkan logik penghantaran daripada pesanan; satu pesanan = satu penghantaran.

#### `status_logs`
- **Tujuan:** Jejak audit setiap perubahan status pesanan.
- **Primary Key:** `id`. **Foreign Key:** `order_id → orders.id`.
- **Lajur penting:** `old_status`, `new_status`, `changed_by` (user id, boleh NULL untuk tindakan sistem/pelanggan), `changed_at`.
- **Mengapa diperlukan:** Ketelusan & penyelesaian pertikaian (siapa ubah status, bila).

#### `notifications`
- **Tujuan:** Pemberitahuan peringkat restoran (cth: "Order Baru!").
- **Primary Key:** `id`. **Foreign Key:** `restaurant_id → restaurants.id`.
- **Lajur penting:** `title`, `message`, `type` ENUM(`order`,`delivery`,`system`), `is_read`.
- **Mengapa diperlukan:** Memberi maklum balas masa hampir nyata kepada Admin/Staff.

### 4.3 Hubungan Antara Jadual (Entity Relationship)

```
restaurants (1) ─────< (∞) users           [restaurant_id]
restaurants (1) ─────< (∞) runners          [restaurant_id]
restaurants (1) ─────< (∞) categories       [restaurant_id]
restaurants (1) ─────< (∞) menu_items       [restaurant_id]
restaurants (1) ─────< (∞) orders           [restaurant_id]
restaurants (1) ─────< (∞) notifications    [restaurant_id]

users       (1) ─────< (∞) runners          [user_id]
categories  (1) ─────< (∞) menu_items       [category_id]

orders      (1) ─────< (∞) order_items      [order_id]
orders      (1) ─────< (1) deliveries       [order_id]
orders      (1) ─────< (∞) status_logs      [order_id]

runners     (1) ─────< (∞) deliveries       [runner_id]
users       (1) ─────< (∞) deliveries       [assigned_by]
```

- **`restaurants`** ialah pusat seni bina multi-tenant — hampir semua jadual merujuk kepadanya.
- **`orders`** ialah pusat aliran transaksi — disambung kepada `order_items`, `deliveries`, dan `status_logs`.
- Pengasingan tenant dicapai dengan **sentiasa** menapis pertanyaan menggunakan `restaurant_id` yang diperoleh daripada `activeRestaurantId()` / `custRestaurantId()`.

---

## 5. Module Explanation

Setiap modul diterangkan dengan: **Tujuan, Fungsi, Input, Output, Database, Flow proses, Validation, Error handling.**

### 5.1 Customer Module

#### (a) Restaurant Listing — `customer/customer_restaurants.php`
- **Tujuan:** Direktori semua restoran aktif.
- **Fungsi:** Memaparkan kad restoran (cover, nama, status buka/tutup) + carian langsung (live search).
- **Input:** (Pilihan) teks carian; klik pada restoran.
- **Output:** Senarai restoran; klik menetapkan `cust_restaurant_id` dan menuju ke menu.
- **Database:** `restaurants` (WHERE `is_active = 1`).
- **Flow:** Muat halaman → tarik restoran aktif → render kad → carian ditapis di klien (JavaScript).
- **Validation/Error:** Jika tiada restoran aktif, paparan kosong yang sesuai.

#### (b) Menu — `customer/customer_menu.php` + `api/customer_get_menu.php`
- **Tujuan:** Memaparkan menu restoran terpilih mengikut kategori.
- **Fungsi:** Senarai item (gambar, nama, harga), butang "Tambah ke troli".
- **Input:** `restaurant` (id) melalui URL/session.
- **Output:** Menu dirender; tindakan tambah ke troli memanggil `api/customer_cart.php`.
- **Database:** `categories`, `menu_items` (ditapis `restaurant_id`, `is_available = 1`).
- **Validation/Error:** Item tidak tersedia tidak boleh ditambah.

#### (c) Cart (Troli) — `customer/customer_cart.php` + `api/customer_cart.php`
- **Tujuan:** Mengurus item sebelum checkout.
- **Fungsi:** Tambah / kemas kini kuantiti / buang item; kira subtotal, yuran penghantaran, jumlah; papar thumbnail item.
- **Input:** Tindakan `add` / `update` / `remove` / `clear` / `get`.
- **Output:** JSON {`total_items`, `total`, `items[]`}.
- **Database:** `menu_items` (untuk sahkan item & dapatkan gambar). Troli sendiri disimpan dalam `$_SESSION['cart']`.
- **Flow:** Klik tambah → AJAX POST → PHP sahkan item milik restoran semasa → simpan ke session → pulang JSON → UI kemas kini.
- **Validation:** `item_id` mesti milik restoran semasa & `is_available`.
- **Error handling:** Pulangkan `{success:false, message}` jika item tidak sah / restoran belum dipilih.

#### (d) Checkout — `customer/customer_cart.php` → `api/customer_place_order.php`
- **Tujuan:** Menukar troli menjadi pesanan sebenar.
- **Input (POST):** `customer_name`, `customer_phone`, `delivery_address`, `payment_method`, `notes`, `items` (JSON).
- **Output:** JSON {`order_no`, `order_id`, `total`, `payment_method`} → halaman kejayaan.
- **Database:** Tulis ke `orders`, `order_items`, `notifications`, `status_logs` dalam **satu transaksi**.
- **Flow proses (backend):**
  1. Sahkan medan wajib & format (nama ≥ 3 aksara, alamat ≥ 10 aksara, telefon `^(01)[0-9]{8,9}$`).
  2. Sahkan setiap item terhadap pangkalan data (harga & ketersediaan diambil semula dari DB — **bukan** dipercayai dari klien).
  3. Kira `subtotal`, `delivery_fee` (percuma jika cecah `free_delivery_min`), `total`.
  4. Jana `order_no` unik (`generateOrderNo()`).
  5. `BEGIN TRANSACTION` → masukkan `orders` → masukkan setiap `order_items` → tambah notifikasi "Order Baru!" → log status `null → pending` → `COMMIT`.
  6. Kosongkan troli session.
- **Validation:** Berlapis (medan, format, pengesahan item DB).
- **Error handling:** Jika mana-mana langkah gagal → `ROLLBACK` & pulangkan mesej ralat (tiada data separa disimpan).

#### (e) Order Tracking — `customer/customer_track.php` + `api/customer_get_tracking.php`
- **Tujuan:** Membenarkan pelanggan menyemak status pesanan.
- **Input:** `order_no`.
- **Output:** Status semasa, garis masa (timeline), maklumat penghantaran, peta lokasi (embed).
- **Database:** `orders`, `order_items`, `deliveries`, `runners`.
- **Flow:** Pelanggan masukkan `order_no` → AJAX → JSON status → UI kemas kini garis masa.

#### (f) Order Success — `customer/customer_order_success.php`
- **Tujuan:** Pengesahan selepas pesanan; papar `order_no`, ringkasan item, jumlah, dan arahan bayaran (tunai/bank).

> **ℹ️ Belum wujud dalam modul pelanggan:** Homepage berasingan pelanggan log masuk, Order History peribadi, Notifications peribadi, Profile, dan Review (lihat Seksyen 14). "Homepage" awam ialah `landing/index.html` melalui `index.php`.

### 5.2 Admin Module

| Submodul | Fail | Tujuan |
| --- | --- | --- |
| Dashboard | `admin/admin_dashboard.php` + `api/admin_get_stats.php` | Statistik restoran (jumlah pesanan, jualan, status). |
| Menu & Kategori | `admin/admin_menu.php` + `api/admin_edit_menu.php` | CRUD item menu (termasuk muat naik gambar) & kategori. |
| Order Management | `admin/admin_orders.php` + `api/admin_get_orders.php` | Lihat & urus pesanan restoran. |
| Assign Runner | `admin/admin_assign.php` + `api/admin_assign_runner.php` | Tugaskan runner kepada pesanan `pending`. |
| Tracking | `admin/admin_tracking.php` | Pantau penghantaran (dengan peta). |
| Runner Management | `admin/admin_runners.php` + `api/admin_get_runners.php`, `api/admin_edit_runner.php` | Urus runner restoran. |
| Reports | `admin/admin_reports.php` | Laporan jualan/pesanan restoran. |
| Notifications | `admin/admin_notifications.php` + `api/admin_get_notifications.php`, `api/admin_mark_notification.php` | Senarai & tanda notifikasi dibaca. |
| Profile | `admin/admin_profile.php` | Kemas kini profil & kata laluan admin. |

- **Input/Output umum:** Borang & tindakan AJAX → JSON. Semua pertanyaan **ditapis `activeRestaurantId()`**.
- **Validation:** Medan wajib, jenis & saiz gambar (lihat 9.8), kewujudan rekod.
- **Error handling:** Mesej ralat mesra; transaksi untuk operasi kritikal (assign).

> **ℹ️ Nota:** "Customer Management" & "Analytics" lanjutan yang disebut dalam keperluan **tidak** wujud sebagai modul penuh (pelanggan ialah tetamu). Laporan asas tersedia melalui `admin_reports.php`.

### 5.3 Staff Module
| Submodul | Fail | Tujuan |
| --- | --- | --- |
| Dashboard | `staff/staff_dashboard.php` | Ringkasan ringkas + notifikasi belum dibaca/hari ini. |
| Notifications | `staff/staff_notifications.php` + `api/staff_get_notifications.php`, `api/staff_mark_notification.php` | Lihat & tanda notifikasi. |
| Profile | `staff/staff_profile.php` | Kemas kini nama/telefon/kata laluan. |

- **Capaian:** Dikuatkuasakan oleh `staff_auth.php` & `requireStaffApi()`.
- **ℹ️ Nota:** "Incoming Orders / Accept / Prepare / Update Status" ialah **konsep** — belum dilaksanakan untuk Staff (lihat 2.3 & Seksyen 14).

### 5.4 Runner Module
| Submodul | Fail | Tujuan |
| --- | --- | --- |
| Assigned Orders | `runner/runner_orders.php` | Senarai pesanan yang di-assign + togol online/offline. |
| Order Detail | `runner/runner_order_detail.php` | Butiran pesanan + peta + butang navigasi. |
| Update Status | `api/runner_update_status.php` | Peralihan `picked_up → in_transit → delivered`. |
| Toggle Availability | `api/runner_toggle.php` | Tukar status `online`/`offline`. |
| History | `runner/runner_history.php` + `api/runner_get_history.php` | Sejarah penghantaran selesai. |
| Profile | `runner/runner_profile.php` | Profil runner. |

- **Flow proses (update status):** Lihat Seksyen 6.
- **Validation:** Peralihan status mesti mengikut turutan sah (`assigned→picked_up→in_transit→delivered`); delivery mesti milik runner tersebut.
- **Error handling:** Pulangkan JSON ralat & `ROLLBACK` jika gagal.

> **ℹ️ "Earnings" (pendapatan runner) belum wujud** — lihat Seksyen 14.

### 5.5 Super Admin Module
| Submodul | Fail | Tujuan |
| --- | --- | --- |
| Store Management | `superadmin/superadmin_stores.php` | CRUD restoran + penjenamaan + muat naik logo/cover. |
| User Management | `superadmin/superadmin_users.php` | Cipta/edit pengguna, reset kata laluan, tetapkan restoran. |
| Dashboard (Global) | `superadmin/superadmin_dashboard.php` | Statistik agregat + pecahan setiap restoran. |
| Profile | `superadmin/superadmin_profile.php` | Profil Sistem Owner. |

> **ℹ️ "Subscription", "Tenant billing", "System Monitoring", dan "Logs" lanjutan** ialah cadangan (Seksyen 14). Jejak audit asas wujud melalui `status_logs`.

---

## 6. Order Workflow

Aliran lengkap satu pesanan, dengan proses backend pada setiap langkah:

```
[Customer Checkout]
   status pesanan: pending
   ── orders + order_items dimasukkan (transaksi)
   ── notifications: "Order Baru!" (restaurant_id)
   ── status_logs: null → pending
            │
            ▼
[Restoran (Admin/Staff) dimaklumkan]
   ── admin.js / staff.js poll api/*_get_notifications.php
   ── lonceng notifikasi dikemas kini
            │
            ▼
[Admin assign Runner]   (api/admin_assign_runner.php)
   status pesanan: pending → assigned
   ── deliveries dicipta (status 'assigned', assigned_by = admin)
   ── runners.status → busy
   ── status_logs: pending → assigned
   ── notifications: "Runner Assigned"
            │
            ▼
[Runner: "Saya Dah Ambil"]   (api/runner_update_status.php)
   delivery: assigned → picked_up (picked_up_at = NOW)
   order:    assigned → picked_up
   ── status_logs direkod
            │
            ▼
[Runner: "Dalam Perjalanan"]
   delivery: picked_up → in_transit
   order:    picked_up → in_transit
   ── Runner buka Google Maps (butang Navigate → maps.google.com?q=alamat)
            │
            ▼
[Runner: "Dah Dihantar"]
   delivery: in_transit → delivered (delivered_at = NOW)
   order:    in_transit → delivered
   ── runners.status → online (jika tiada tugasan aktif lain) / busy
   ── notifications: "Order Delivered"
   ── status_logs direkod
            │
            ▼
[Customer melihat status terkini]
   ── customer_track.php menarik status melalui api/customer_get_tracking.php
            │
            ▼
[Order Selesai]
```

**Nota penting tentang realiti pelaksanaan:**
- Peralihan status **dikuatkuasakan secara ketat** di backend. Contoh: `runner_update_status.php` hanya membenarkan langkah seterusnya yang sah (`$flow[$current] === $new`). Lompat status tidak dibenarkan.
- Setiap kemas kini kritikal dibungkus dalam **transaksi** (`BEGIN/COMMIT/ROLLBACK`) supaya pangkalan data sentiasa konsisten.
- Langkah "Staff menyediakan makanan / accept order" dalam aliran ideal **belum** mempunyai tindakan khusus dalam kod (Staff read-only); Admin terus assign runner kepada pesanan `pending`.

---

## 7. Notification System

- **Storan:** Jadual `notifications` (peringkat **restoran**, bukan per pengguna).
- **Siapa menerima:** Admin & Staff restoran berkenaan (mereka berkongsi aliran notifikasi restoran yang sama).
- **Bila dihantar (dicipta oleh backend):**
  - Pesanan baharu dibuat → `"Order Baru!"` (jenis `order`).
  - Runner di-assign → `"Runner Assigned"` (jenis `delivery`).
  - Pesanan dihantar → `"Order Delivered"` (jenis `delivery`).
  - Mesej sistem → jenis `system` (cth notifikasi selamat datang dalam data benih).
- **Jenis notifikasi:** `order`, `delivery`, `system`.
- **Flow notifikasi:**
  1. Peristiwa backend memanggil `addNotification($conn, title, message, type, restaurant_id)`.
  2. Frontend (admin/staff) memanggil endpoint `*_get_notifications.php` secara berkala (polling AJAX) untuk mengira bilangan belum dibaca & memaparkannya.
  3. Pengguna boleh menanda satu / semua sebagai dibaca (`*_mark_notification.php`), mengemas kini `is_read`.

> **ℹ️ Nota:** Notifikasi pelanggan secara langsung (mis. push) **belum** wujud — pelanggan menyemak status melalui halaman penjejakan. Push/realtime sebenar dicadangkan dalam Seksyen 14.

---

## 8. Google Maps Integration

**Realiti pelaksanaan semasa (penting):** Tracky menggunakan **Google Maps secara *embed* mudah melalui URL**, **tanpa API key** dan **tanpa** Google Maps JavaScript/Directions API.

- **Lokasi pelanggan (Customer Location):** Diambil daripada teks `delivery_address` yang ditaip pelanggan (bukan koordinat GPS).
- **Paparan peta:** Menggunakan `iframe` —
  `https://maps.google.com/maps?q=<alamat>&output=embed`
  dipaparkan dalam:
  - `customer/customer_track.php` (pelanggan nampak lokasi penghantaran),
  - `admin/admin_tracking.php` (admin pantau),
  - `runner/runner_order_detail.php` (runner lihat peta).
- **Navigasi Runner:** Butang **"Navigate"** ialah pautan ke
  `https://maps.google.com/?q=<alamat>` yang membuka aplikasi/halaman Google Maps untuk navigasi sebenar pada peranti runner.

**Apa yang BELUM ada (kerana tiada Directions API):**
- **Direction API** sebenar (laluan dilukis dalam app) — tiada.
- **Distance** (jarak dikira) — tiada.
- **ETA** (anggaran masa tiba dikira) — tiada.
- **Live GPS tracking** kedudukan runner masa nyata — tiada.

> Ringkasnya: integrasi peta kini bersifat **paparan & pautan navigasi** menggunakan alamat teks. Untuk Direction/Distance/ETA/GPS sebenar, perlukan Google Maps Platform API key + Directions/Distance Matrix/Geolocation API (lihat Seksyen 14).

---

## 9. System Security

### 9.1 Password Hashing
- Kata laluan **tidak pernah** disimpan sebagai teks biasa.
- Menggunakan `password_hash()` (algoritma **bcrypt** lalai PHP) semasa cipta/reset.
- Disahkan dengan `password_verify()` semasa log masuk.

### 9.2 SQL Injection Prevention
- **Semua** pertanyaan yang melibatkan input pengguna menggunakan **prepared statements** (`mysqli_prepare` + `mysqli_stmt_bind_param`).
- Input tidak pernah digabung terus ke dalam rentetan SQL.

### 9.3 XSS Prevention
- Output dinamik di-*escape* menggunakan fungsi pembantu `e()` =
  `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Ini menghalang skrip jahat daripada dilaksanakan dalam pelayar.

### 9.4 CSRF Protection
> **ℹ️ Belum dilaksanakan.** Borang & endpoint AJAX **belum** menggunakan token CSRF. Ini ialah **jurang keselamatan yang diketahui** dan disyorkan untuk dibaiki sebelum produksi (lihat Seksyen 14). Mitigasi separa: endpoint menggunakan session + semakan peranan, dan `fetch` menggunakan `credentials: 'same-origin'`.

### 9.5 Session Security
- Identiti pengguna disimpan di pelayan (`$_SESSION`), bukan di klien.
- Setiap halaman terlindung mengesahkan session melalui auth guard.
- Logout memusnahkan session.
> Cadangan pengukuhan: `session_regenerate_id()` selepas login, cookie `HttpOnly`/`Secure`/`SameSite` (Seksyen 14).

### 9.6 Access Control (RBAC)
- Guard halaman (`*_auth.php`) + guard API (`requireAdminApi`, `requireStaffApi`, `requireRunnerApi`).
- Pengasingan tenant: setiap pertanyaan ditapis `restaurant_id`, jadi seorang admin tidak boleh melihat atau mengubah data restoran lain walaupun meneka URL/ID.

### 9.7 Input Validation
- Pengesahan **pelayan** (server-side) untuk data kritikal — cth `customer_place_order.php`: panjang nama/alamat, regex telefon `^(01)[0-9]{8,9}$`, kaedah bayaran dalam senarai putih, dan **pengesahan semula harga/ketersediaan item dari DB**.
- Pengesahan **klien** (JavaScript) sebagai lapisan UX (maklum balas pantas), tetapi tidak dipercayai bersendirian.

### 9.8 File Upload Validation
Untuk muat naik gambar (`uploadMenuImage()` / `uploadImageTo()` dalam `includes/functions.php`):
- Semakan saiz maksimum (**3MB**).
- Semakan **MIME sebenar** menggunakan `finfo` (bukan sekadar sambungan fail).
- Hanya jenis dibenarkan: **JPG, PNG, WEBP, GIF**.
- Nama fail dijana semula secara rawak (elak pertindihan & nama berbahaya).

---

## 10. UI/UX Concept

### 10.1 Design Theme
- **Tema sebenar:** Aksen **hijau** (`#1D9E75`) di atas latar neutral, dengan **mod terang & gelap (light/dark mode)** yang boleh ditukar pengguna (disimpan dalam `localStorage`, dikendalikan oleh `assets/js/theme.js`).
- Font: **Inter**; ikon: **Tabler Icons**; rangka kerja UI: **Bootstrap 5**.

> **ℹ️ Nota:** Keperluan menyebut tema **Black, Gold & White**. Tema semasa ialah **hijau + neutral + light/dark**. Penjenamaan hitam/emas/putih boleh dilaksanakan sebagai pilihan tema baharu (Seksyen 14) memandangkan warna aksen sudah berpusat pada pemboleh ubah CSS (`--green` / `accent_color`).

### 10.2 Responsive Design
- Reka letak responsif menggunakan grid Bootstrap + media queries tersuai, sesuai untuk desktop, tablet, dan telefon.
- Panel runner direka mesra mudah alih (navigasi bawah).

### 10.3 Animation
- Animasi kemasukan (fade/slide/scale) pada halaman utama & direktori restoran (CSS keyframes), kesan *hover*, dan peralihan lembut untuk pengalaman premium.

### 10.4 User Experience & Navigation
- Aliran pelanggan yang lurus: direktori → menu → troli → checkout → kejayaan → jejak.
- Pautan "Home"/logo sentiasa kembali ke laman utama; butang konteks (cth "Tukar restoran").
- Setiap panel mempunyai sidebar/topbar konsisten mengikut peranan.

### 10.5 Accessibility
- Penggunaan label borang, atribut `aria-label` pada elemen carian, kontras warna yang munasabah, dan saiz sasaran sentuh yang sesuai.
> Cadangan: audit aksesibiliti penuh (WCAG) — Seksyen 14.

---

## 11. Folder Structure

**Struktur sebenar projek (semasa):**

```
tracky/
├── index.php                 # Titik masuk: redirect ikut peranan / paparkan landing
├── login.php                 # Log masuk semua peranan berakaun
├── logout.php                # Musnah session
├── README.md
├── landing/
│   └── index.html            # Laman pemasaran awam
├── customer/                 # Modul pelanggan (tetamu)
│   ├── customer_restaurants.php
│   ├── customer_menu.php
│   ├── customer_cart.php
│   ├── customer_order_success.php
│   └── customer_track.php
├── admin/                    # Panel admin restoran
├── staff/                    # Panel staf (terhad)
├── runner/                   # Panel penghantar
├── superadmin/               # Panel Sistem Owner
├── api/                      # Endpoint JSON (admin_*, customer_*, runner_*, staff_*)
├── includes/                 # Logik kongsi:
│   ├── db.php                #   sambungan pangkalan data
│   ├── functions.php         #   fungsi bantuan (asset, e, auth API, upload, dll)
│   ├── *_auth.php            #   auth guard setiap peranan
│   └── *_layout_start/end.php#   susun atur (header/footer) setiap panel
├── assets/
│   ├── css/                  # admin.css, customer.css, runner.css
│   ├── js/                   # admin.js, customer.js, staff.js, theme.js
│   ├── img/                  # logo & ikon
│   └── uploads/              # gambar restoran (restaurants/) & menu (menu/)
└── database/
    ├── tracky.sql            # Skema + data benih
    ├── migrate_multitenant.php
    └── seed_restaurant2.php
```

**Fungsi setiap folder:**
- **`admin/`, `staff/`, `runner/`, `superadmin/`, `customer/`** — modul ikut peranan; setiap halaman PHP melindungi diri dengan auth guard yang sesuai.
- **`api/`** — endpoint yang memulangkan **JSON** untuk AJAX; diberi nama mengikut peranan + tindakan.
- **`includes/`** — kod kongsi: sambungan DB, fungsi bantuan, penjaga keizinan, dan susun atur UI.
- **`assets/`** — fail statik: `css/`, `js/`, `img/`, dan `uploads/` (gambar dimuat naik).
- **`database/`** — skrip skema & benih.
- **`landing/`** — laman utama awam.

> **ℹ️ Tentang `config/`, `controllers/`, `models/`, `vendor/`:** Folder ini **tidak** wujud dalam projek semasa kerana ia ialah aplikasi PHP prosedural tanpa Composer/MVC. Peranan "config" kini dimainkan oleh `includes/db.php`; peranan "models/controllers" diserap ke dalam halaman PHP + `includes/functions.php`. Penstrukturan semula kepada `config/`, `controllers/`, `models/`, `vendor/` ialah cadangan penambahbaikan seni bina (lihat Seksyen 14).

---

## 12. Backend Flow

Aliran umum data bagi satu permintaan tipikal:

```
Browser  ──HTTP(GET/POST atau fetch)──▶  PHP (halaman / api)
                                            │
                                            ├─ session_start() + auth guard (semak peranan)
                                            │
                                            ├─ Baca input ($_POST / $_GET / php://input JSON)
                                            │
                                            ├─ VALIDATION (medan wajib, format, senarai putih)
                                            │
                                            ├─ Logik perniagaan
                                            │     └─ mysqli prepared statement ──▶ MySQL
                                            │          (SELECT/INSERT/UPDATE, transaksi jika kritikal)
                                            │
                                            ├─ Bina RESPONSE (HTML dirender ATAU JSON)
                                            ▼
Browser  ◀──Response──  (HTML penuh)  atau  (JSON → JavaScript kemas kini DOM)
```

Contoh konkrit (checkout):
`customer_cart.php (JS)` → `fetch POST api/customer_place_order.php` → PHP **validate** → **transaksi** tulis `orders`+`order_items`+`notifications`+`status_logs` → JSON `{order_no}` → JS **redirect** ke `customer_order_success.php`.

---

## 13. API & AJAX Flow

- **Bentuk permintaan:** Frontend menggunakan **Fetch API**. Kebanyakan tindakan menghantar `POST` (borang URL-encoded atau JSON body), `credentials: 'same-origin'` untuk membawa cookie session.
- **Bentuk respons:** Setiap endpoint dalam `api/` memulangkan **JSON** dengan corak konsisten:
  ```json
  { "success": true,  "...": "data" }
  { "success": false, "message": "sebab ralat" }
  ```
- **Real-time update (polling):**
  - Lonceng notifikasi admin/staff: JavaScript memanggil `*_get_notifications.php` secara berkala dan mengemas kini kiraan.
  - Penjejakan pesanan pelanggan: `customer_track.php` menarik status terkini melalui `api/customer_get_tracking.php`.
  - Dashboard: statistik disegarkan melalui `api/admin_get_stats.php`.
- **Order tracking (aliran data):** Pelanggan masukkan `order_no` → fetch → PHP cari `orders`+`deliveries`+`status_logs` → JSON status & garis masa → JS render timeline.

> **ℹ️ Nota:** "Real-time" di sini bermaksud **polling berkala (AJAX)**, bukan WebSocket/push sebenar. Push masa nyata dicadangkan dalam Seksyen 14.

---

## 14. Future Improvements

Cadangan ciri profesional untuk versi akan datang (dikelaskan ikut keutamaan & nilai):

### 14.1 Keselamatan & Seni Bina (keutamaan tinggi)
- **CSRF protection** (token pada semua borang & endpoint POST).
- **Pengukuhan session** (`session_regenerate_id`, cookie `HttpOnly`/`Secure`/`SameSite`).
- **Penstrukturan semula** kepada `config/`, `controllers/`, `models/`, autoload Composer (`vendor/`), dan satu pusat konfigurasi `BASE_URL` (menggantikan path `/tracky/` yang ditulis keras).
- **HTTPS** wajib di produksi + konfigurasi `.env` untuk kelayakan DB.

### 14.2 Ciri Pelanggan
- **Akaun pelanggan** + pendaftaran + **Order History** + **Profile**.
- **Review & Rating** restoran/menu.
- **QR Order** (imbas QR di meja untuk terus ke menu restoran).
- **Online Payment** (gateway: Stripe/iPay88/ToyyibPay) menggantikan COD/pindahan manual.
- **Loyalty Point & Voucher / Promo code**.
- **Multi-language** (BM/EN) & penambahbaikan **Dark Mode**.

### 14.3 Operasi & Penghantaran
- **Google Maps Platform sebenar**: Directions API (laluan), Distance Matrix (jarak), ETA, dan **GPS tracking runner masa nyata**.
- **Aliran Staff penuh**: terima pesanan, "sedang masak", kemas kini status dapur.
- **Runner Earnings** & **Driver Performance** (KPI penghantaran).
- **Inventory / Stock Prediction**.

### 14.4 Notifikasi & Masa Nyata
- **Push Notification** (Web Push / FCM) & **Live Chat** (pelanggan ↔ runner/restoran).
- **WebSocket** untuk kemas kini status segera (gantikan polling).

### 14.5 Analitik & Perniagaan (Super Admin)
- **Subscription / Tenant Billing** untuk restoran.
- **Analytics Dashboard** lanjutan, **Sales Prediction (AI)**, **AI Recommendation** menu, dan **Heat Map** kawasan permintaan tinggi.
- **System Monitoring & Logs** terpusat.

---

> **Penutup.** Dokumen ini menggambarkan Tracky sebagaimana ia wujud dalam kod sumber pada masa penulisan, beserta cadangan hala tuju. Falsafah reka bentuk teras — **pengasingan multi-tenant melalui `restaurant_id`**, **kawalan capaian berasaskan peranan**, **transaksi pangkalan data untuk operasi kritikal**, dan **pengesahan di pelayan** — ialah tunjang yang menjadikan sistem ini selamat, konsisten, dan boleh dikembangkan.
