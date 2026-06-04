CREATE DATABASE IF NOT EXISTS tracky_db;
USE tracky_db;

CREATE TABLE restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL DEFAULT 'Restoran Tracky',
  address TEXT,
  phone VARCHAR(20),
  email VARCHAR(100),
  delivery_fee DECIMAL(10,2) DEFAULT 5.00,
  free_delivery_min DECIMAL(10,2) DEFAULT 30.00,
  operating_hours VARCHAR(100) DEFAULT '8:00 AM - 10:00 PM',
  is_open TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('superadmin','admin','staff','runner') NOT NULL,
  phone VARCHAR(20),
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE runners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  vehicle_no VARCHAR(20),
  phone VARCHAR(20),
  status ENUM('online','offline','busy') DEFAULT 'offline',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  is_active TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  is_available TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('order','delivery','system') DEFAULT 'order',
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO restaurants (name, address, phone, email) VALUES
('Restoran Selera Nusantara',
'No 5, Jalan Merdeka, Ayer Keroh, 75450 Melaka',
'+60 6-234 5678',
'info@seleranusantara.com');

INSERT INTO users (name, email, password, role, phone) VALUES
('Super Admin', 'superadmin@tracky.com', '$2y$10$Ao0f6r6FUgM9UFIdSlw4X.noRyYPqRsHHaCQx5rXBJTSGzBXZpQaC', 'superadmin', '+60 12-000 0000'),
('Admin Restoran', 'admin@tracky.com', '$2y$10$pg/rjTa3qk.Pdi1QYjqrHOcgA3MCsnP9eGVGZiKwFp7eMdCMRkJaG', 'admin', '+60 12-111 1111'),
('Ahmad Runner', 'runner1@tracky.com', '$2y$10$JBO8zIgcyHKj..9v1PlzSuhbkuQZWOEbBCcW0pxOUorQIb4CBB8jK', 'runner', '+60 12-222 2222'),
('Siti Runner', 'runner2@tracky.com', '$2y$10$JBO8zIgcyHKj..9v1PlzSuhbkuQZWOEbBCcW0pxOUorQIb4CBB8jK', 'runner', '+60 12-333 3333');

INSERT INTO runners (user_id, vehicle_no, phone, status) VALUES
(3, 'MKM 1234', '+60 12-222 2222', 'online'),
(4, 'MKM 5678', '+60 12-333 3333', 'online');

INSERT INTO categories (name, description, is_active, sort_order) VALUES
('Nasi & Lauk', 'Hidangan nasi pelbagai pilihan', 1, 1),
('Mee & Bihun', 'Mee goreng, mee sup dan banyak lagi', 1, 2),
('Minuman', 'Minuman sejuk dan panas', 1, 3),
('Dessert', 'Pencuci mulut pilihan', 1, 4);

INSERT INTO menu_items (category_id, name, description, price, is_available) VALUES
(1, 'Nasi Lemak Ayam', 'Nasi lemak dengan ayam goreng, sambal, telur dan timun', 12.00, 1),
(1, 'Nasi Goreng Kampung', 'Nasi goreng dengan telur, sayur dan ikan bilis', 10.00, 1),
(1, 'Nasi Ayam Hainan', 'Nasi ayam dengan sup dan sos halia', 13.00, 1),
(1, 'Nasi Campur', 'Nasi putih dengan pilihan lauk pelbagai', 9.00, 1),
(2, 'Mee Goreng Mamak', 'Mee goreng dengan telur, tauhu dan udang', 9.00, 1),
(2, 'Mee Sup Daging', 'Mee dalam sup daging yang pekat', 11.00, 1),
(2, 'Bihun Goreng', 'Bihun goreng dengan sayur dan telur', 8.00, 1),
(3, 'Teh Tarik', 'Teh tarik panas atau sejuk', 3.50, 1),
(3, 'Milo Ais', 'Milo sejuk yang menyegarkan', 4.00, 1),
(3, 'Air Kosong', 'Air mineral sejuk', 1.50, 1),
(4, 'Cendol', 'Cendol dengan santan dan gula melaka', 5.00, 1),
(4, 'Ais Krim Potong', 'Ais krim potong pelbagai perisa', 3.00, 1);

INSERT INTO notifications (title, message, type, is_read) VALUES
('Selamat Datang', 'Sistem Tracky telah berjaya dipasang', 'system', 0);
