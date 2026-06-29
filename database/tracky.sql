CREATE DATABASE IF NOT EXISTS tracky_db;
USE tracky_db;

CREATE TABLE restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL DEFAULT 'Restoran Tracky',
  slug VARCHAR(80) UNIQUE,
  address TEXT,
  phone VARCHAR(20),
  email VARCHAR(100),
  logo VARCHAR(255),
  cover_image VARCHAR(255),
  accent_color VARCHAR(20) NOT NULL DEFAULT '#1D9E75',
  delivery_fee DECIMAL(10,2) DEFAULT 5.00,
  free_delivery_min DECIMAL(10,2) DEFAULT 30.00,
  operating_hours VARCHAR(100) DEFAULT '8:00 AM - 10:00 PM',
  is_open TINYINT DEFAULT 1,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('superadmin','admin','staff','runner') NOT NULL,
  phone VARCHAR(20),
  restaurant_id INT NULL,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_restaurant (restaurant_id),
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)
);

CREATE TABLE runners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  restaurant_id INT NULL,
  vehicle_no VARCHAR(20),
  phone VARCHAR(20),
  status ENUM('online','offline','busy') DEFAULT 'offline',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_runners_restaurant (restaurant_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  is_active TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0,
  INDEX idx_categories_restaurant (restaurant_id),
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NULL,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  image VARCHAR(255) NULL,
  price DECIMAL(10,2) NOT NULL,
  is_available TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_menu_items_restaurant (restaurant_id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NULL,
  order_no VARCHAR(20) UNIQUE NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  delivery_address TEXT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  delivery_fee DECIMAL(10,2) DEFAULT 5.00,
  total_amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('cash','online') DEFAULT 'cash',
  payment_status ENUM('pending','paid') DEFAULT 'pending',
  status ENUM('pending','assigned','picked_up','in_transit','delivered','cancelled') DEFAULT 'pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_restaurant (restaurant_id),
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

CREATE TABLE deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  runner_id INT NOT NULL,
  assigned_by INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  picked_up_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL,
  status ENUM('assigned','picked_up','in_transit','delivered') DEFAULT 'assigned',
  notes TEXT,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (runner_id) REFERENCES runners(id),
  FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE status_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  old_status VARCHAR(20),
  new_status VARCHAR(20),
  changed_by INT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('order','delivery','system') DEFAULT 'order',
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_restaurant (restaurant_id),
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)
);

-- ── Restaurants (multi-tenant) ──
INSERT INTO restaurants (id, name, slug, address, phone, email, accent_color, cover_image) VALUES
(1, 'Restoran Selera Nusantara', 'restoran-selera', 'No 5, Jalan Merdeka, Ayer Keroh, 75450 Melaka', '+60 6-234 5678', 'info@seleranusantara.com', '#1D9E75', 'assets/uploads/restaurants/cover_1.jpg'),
(2, 'Warung Pak Abu', 'warung-pak-abu', 'No 12, Jalan Hang Tuah, 75300 Melaka', '+60 6-987 6543', 'hello@warungpakabu.com', '#1D9E75', 'assets/uploads/restaurants/cover_2.jpg');

-- ── Users ──
-- superadmin: password = superadmin123  | admin: admin123 | runner: runner123 | staff: staff123
INSERT INTO users (name, email, password, role, phone, restaurant_id) VALUES
('System Owner', 'superadmin@tracky.com', '$2y$10$mEx1BVp9WxDfVnz5febSvOQkzbf0rdjPoh22iJbyfAuvelWasY1S.', 'superadmin', '+60 12-000 0000', NULL),
('Admin Selera', 'admin@tracky.com', '$2y$10$pg/rjTa3qk.Pdi1QYjqrHOcgA3MCsnP9eGVGZiKwFp7eMdCMRkJaG', 'admin', '+60 12-111 1111', 1),
('Ahmad Runner', 'runner1@tracky.com', '$2y$10$JBO8zIgcyHKj..9v1PlzSuhbkuQZWOEbBCcW0pxOUorQIb4CBB8jK', 'runner', '+60 12-222 2222', 1),
('Siti Runner', 'runner2@tracky.com', '$2y$10$JBO8zIgcyHKj..9v1PlzSuhbkuQZWOEbBCcW0pxOUorQIb4CBB8jK', 'runner', '+60 12-333 3333', 1),
('Admin Pak Abu', 'admin2@tracky.com', '$2y$10$pg/rjTa3qk.Pdi1QYjqrHOcgA3MCsnP9eGVGZiKwFp7eMdCMRkJaG', 'admin', '+60 12-444 4444', 2),
('Zaki Runner', 'runner3@tracky.com', '$2y$10$JBO8zIgcyHKj..9v1PlzSuhbkuQZWOEbBCcW0pxOUorQIb4CBB8jK', 'runner', '+60 12-555 5555', 2),
('Staf Selera', 'staffselera@tracky.com', '$2y$10$uKpUe8s0lKgZl9L9TIswIefSvrndw.NS78v1Y.XQMMilpXLy0uZie', 'staff', '+60 12-666 6666', 1),
('Staf Pak Abu', 'staffpakabu@tracky.com', '$2y$10$uKpUe8s0lKgZl9L9TIswIefSvrndw.NS78v1Y.XQMMilpXLy0uZie', 'staff', '+60 12-777 7777', 2);

INSERT INTO runners (user_id, restaurant_id, vehicle_no, phone, status) VALUES
(3, 1, 'MKM 1234', '+60 12-222 2222', 'online'),
(4, 1, 'MKM 5678', '+60 12-333 3333', 'online'),
(6, 2, 'MBA 4321', '+60 12-555 5555', 'online');

-- ── Categories (per restaurant) ──
INSERT INTO categories (id, restaurant_id, name, description, is_active, sort_order) VALUES
(1, 1, 'Nasi & Lauk', 'Hidangan nasi pelbagai pilihan', 1, 1),
(2, 1, 'Mee & Bihun', 'Mee goreng, mee sup dan banyak lagi', 1, 2),
(3, 1, 'Minuman', 'Minuman sejuk dan panas', 1, 3),
(4, 1, 'Dessert', 'Pencuci mulut pilihan', 1, 4),
(5, 2, 'Western', 'Hidangan western pilihan', 1, 1),
(6, 2, 'Minuman', 'Minuman sejuk dan panas', 1, 2);

INSERT INTO menu_items (restaurant_id, category_id, name, description, price, is_available, image) VALUES
(1, 1, 'Nasi Lemak Ayam', 'Nasi lemak dengan ayam goreng, sambal, telur dan timun', 12.00, 1, 'assets/uploads/menu/item_1.jpg'),
(1, 1, 'Nasi Goreng Kampung', 'Nasi goreng dengan telur, sayur dan ikan bilis', 10.00, 1, 'assets/uploads/menu/item_2.jpg'),
(1, 1, 'Nasi Ayam Hainan', 'Nasi ayam dengan sup dan sos halia', 13.00, 1, 'assets/uploads/menu/item_3.jpg'),
(1, 1, 'Nasi Campur', 'Nasi putih dengan pilihan lauk pelbagai', 9.00, 1, 'assets/uploads/menu/item_4.jpg'),
(1, 2, 'Mee Goreng Mamak', 'Mee goreng dengan telur, tauhu dan udang', 9.00, 1, 'assets/uploads/menu/item_5.jpg'),
(1, 2, 'Mee Sup Daging', 'Mee dalam sup daging yang pekat', 11.00, 1, 'assets/uploads/menu/item_6.jpg'),
(1, 2, 'Bihun Goreng', 'Bihun goreng dengan sayur dan telur', 8.00, 1, 'assets/uploads/menu/item_7.jpg'),
(1, 3, 'Teh Tarik', 'Teh tarik panas atau sejuk', 3.50, 1, 'assets/uploads/menu/item_8.jpg'),
(1, 3, 'Milo Ais', 'Milo sejuk yang menyegarkan', 4.00, 1, 'assets/uploads/menu/item_9.jpg'),
(1, 3, 'Air Kosong', 'Air mineral sejuk', 1.50, 1, 'assets/uploads/menu/item_10.jpg'),
(1, 4, 'Cendol', 'Cendol dengan santan dan gula melaka', 5.00, 1, 'assets/uploads/menu/item_11.jpg'),
(1, 4, 'Ais Krim Potong', 'Ais krim potong pelbagai perisa', 3.00, 1, 'assets/uploads/menu/item_12.jpg'),
(2, 5, 'Chicken Chop', 'Ayam dengan kentang dan sos lada hitam', 15.00, 1, 'assets/uploads/menu/item_13.jpg'),
(2, 5, 'Spaghetti Bolognese', 'Spaghetti dengan sos daging', 13.00, 1, 'assets/uploads/menu/item_14.jpg'),
(2, 5, 'Grilled Fish & Chips', 'Ikan panggang dengan kentang goreng', 16.00, 1, 'assets/uploads/menu/item_15.jpg'),
(2, 6, 'Iced Lemon Tea', 'Teh lemon sejuk', 4.50, 1, 'assets/uploads/menu/item_16.jpg'),
(2, 6, 'Kopi O Ais', 'Kopi hitam sejuk', 3.00, 1, 'assets/uploads/menu/item_17.jpg');

INSERT INTO notifications (restaurant_id, title, message, type, is_read) VALUES
(1, 'Selamat Datang', 'Sistem Tracky telah berjaya dipasang', 'system', 0),
(2, 'Selamat Datang', 'Warung Pak Abu kini di atas talian', 'system', 0);
