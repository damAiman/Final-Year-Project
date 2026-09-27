-- =====================================================================
-- SMART STOCK: Digital Inventory Management System
-- Botol Anggun Sdn. Bhd. -- Demo 1 database schema + seed data
--
-- HOW TO USE
--   1. Open phpMyAdmin (http://localhost/phpmyadmin) while XAMPP is running.
--   2. Click "Import", choose this file, and click "Go".
--      (This file creates the database itself, so you do not need to
--       create `smart_stock` manually first.)
--   3. Or, from a terminal:  mysql -u root -p < schema.sql
-- =====================================================================

DROP DATABASE IF EXISTS smart_stock;
CREATE DATABASE smart_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_stock;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- suppliers
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(100) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category_id INT NULL,
  supplier_id INT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  price_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  current_stock INT NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 0,
  storage_location VARCHAR(100) NULL,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- stock_in
-- ---------------------------------------------------------------------
CREATE TABLE stock_in (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  transaction_date DATE NOT NULL,
  remarks VARCHAR(255) NULL,
  user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stockin_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stockin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- stock_out
-- ---------------------------------------------------------------------
CREATE TABLE stock_out (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  transaction_date DATE NOT NULL,
  remarks VARCHAR(255) NULL,
  user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stockout_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stockout_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_stockin_created ON stock_in(created_at);
CREATE INDEX idx_stockout_created ON stock_out(created_at);
CREATE INDEX idx_stockin_txdate ON stock_in(transaction_date);
CREATE INDEX idx_stockout_txdate ON stock_out(transaction_date);
CREATE INDEX idx_stockin_product ON stock_in(product_id);
CREATE INDEX idx_stockout_product ON stock_out(product_id);
CREATE INDEX idx_products_name ON products(name);

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Demo users
-- Password for nurul.huda is: admin123
-- Password for ali.rahman  is: staff123
-- (hashed with PHP password_hash(), algorithm PASSWORD_DEFAULT / bcrypt)
INSERT INTO users (name, username, password, role) VALUES
('Nurul Huda', 'nurul.huda', '$2b$12$tDW57Ffbhf1tNyz9X0YUUeAUCf/ZonLCkXM8bD1msq2NBMZhfGSwi', 'admin'),
('Ali Rahman', 'ali.rahman', '$2b$12$TBj12auMRhkmk6tPsVTWl.8BcPMqv78CTXJsV8BhGIlzUymM.8Gdy', 'staff');

-- Categories
INSERT INTO categories (name) VALUES
('PET Bottles'),
('Glass Bottles'),
('Caps & Closures'),
('Labels & Packaging'),
('Preforms'),
('Carton & Packaging');

-- Suppliers
INSERT INTO suppliers (name, contact_person, phone, email) VALUES
('Sinar Plastik Industries', 'Encik Zamri', '012-3456789', 'sales@sinarplastik.com.my'),
('Kaca Murni Sdn Bhd', 'Puan Farah', '013-2345678', 'info@kacamurni.com.my'),
('Anggun Cap Solutions', 'Encik Hafiz', '014-1234567', 'orders@anggun-caps.com.my'),
('Logam Jaya Sdn Bhd', 'Puan Siti', '016-9876543', 'contact@logamjaya.com.my'),
('PrintCraft Labels', 'Encik Wei Ming', '017-8765432', 'hello@printcraft.com.my'),
('Resin Tech Industries', 'Puan Aisyah', '018-7654321', 'sales@resintech.com.my'),
('Kotak Sejahtera Enterprise', 'Encik Rahim', '019-6543210', 'info@kotaksejahtera.com.my');

-- Products (sample catalogue -- staff can add more via Product Management)
-- price_per_unit = cost price (what Botol Anggun pays the supplier)
-- selling_price  = selling price (what the customer pays)
INSERT INTO products (sku, name, category_id, supplier_id, unit, price_per_unit, selling_price, current_stock, min_stock, storage_location, description, image) VALUES
('BTL-PET-500', 'PET Bottle 500ml', 1, 1, 'pcs', 0.42, 0.75, 18400, 5000, 'Warehouse A - Rack 1', 'Clear PET bottle, 500ml, standard neck finish for beverage filling.', NULL),
('BTL-PET-1000', 'PET Bottle 1L', 1, 1, 'pcs', 0.68, 1.20, 15200, 4000, 'Warehouse A - Rack 2', 'Clear PET bottle, 1 litre, wide base for stability during transport.', NULL),
('BTL-PET-250', 'PET Bottle 250ml', 1, 1, 'pcs', 0.31, 0.58, 9200, 3000, 'Warehouse A - Rack 3', 'Clear PET bottle, 250ml, compact size for travel or trial-size products.', NULL),
('BTL-PET-100', 'PET Bottle 100ml', 1, 1, 'pcs', 0.24, 0.45, 7400, 2500, 'Warehouse A - Rack 3', 'Clear PET bottle, 100ml, ideal for sample or trial-size products.', NULL),
('BTL-HDPE-250', 'HDPE Bottle 250ml', 1, 1, 'pcs', 0.38, 0.70, 6800, 2500, 'Warehouse A - Rack 4', 'Opaque HDPE bottle, 250ml, used for household chemical products.', NULL),
('BTL-SPR-01', 'Spray Pump Bottle', 1, 1, 'pcs', 1.25, 2.20, 310, 500, 'Warehouse A - Rack 5', 'PET bottle with fine-mist spray pump, 100ml.', NULL),
('BTL-GLS-350', 'Glass Bottle 350ml', 2, 2, 'pcs', 1.10, 1.95, 11050, 3000, 'Warehouse B - Rack 1', 'Amber glass bottle, 350ml, for premium beverage packaging.', NULL),
('BTL-GLS-750', 'Glass Bottle 750ml', 2, 2, 'pcs', 1.85, 3.20, 260, 500, 'Warehouse B - Rack 2', 'Clear glass bottle, 750ml, screw-top finish.', NULL),
('JAR-GLS-50', 'Glass Jar 50g', 2, 2, 'pcs', 0.95, 1.70, 480, 800, 'Warehouse B - Rack 3', 'Small glass jar, 50g capacity, wide mouth for cream or paste products.', NULL),
('CAP-PLS-01', 'Bottle Cap - Plastic', 3, 3, 'pcs', 0.08, 0.15, 410, 700, 'Warehouse C - Bay 1', 'Tamper-evident plastic screw cap, fits 28mm neck finish.', NULL),
('CAP-ALU-02', 'Bottle Cap - Aluminium', 3, 4, 'pcs', 0.15, 0.28, 120, 400, 'Warehouse C - Bay 2', 'Roll-on aluminium closure for glass bottles.', NULL),
('CAP-WHT-01', 'Cap White', 3, 3, 'pcs', 0.10, 0.20, 3400, 1000, 'Warehouse C - Bay 1', 'White flip-top cap, fits 24mm neck finish.', NULL),
('CAP-BLK-01', 'Cap Black', 3, 4, 'pcs', 0.10, 0.20, 1150, 900, 'Warehouse C - Bay 2', 'Black flip-top cap, fits 24mm neck finish.', NULL),
('PUMP-WHT-01', 'Pump White', 3, 3, 'pcs', 0.55, 1.00, 2100, 800, 'Warehouse C - Bay 3', 'White dispenser pump top, fits standard 24mm bottle neck.', NULL),
('PUMP-BLK-01', 'Pump Black', 3, 3, 'pcs', 0.55, 1.00, 640, 700, 'Warehouse C - Bay 3', 'Black dispenser pump top, fits standard 24mm bottle neck.', NULL),
('LBL-STD-01', 'Bottle Label - Standard', 4, 5, 'roll', 45.00, 72.00, 860, 200, 'Warehouse D - Rack 1', 'Self-adhesive standard label roll, 1000 labels per roll.', NULL),
('LBL-PRM-02', 'Bottle Label - Premium', 4, 5, 'roll', 78.50, 125.00, 180, 450, 'Warehouse D - Rack 2', 'Foil-finish premium label roll for export product lines.', NULL),
('PRF-28MM', 'PET Preform 28mm', 5, 6, 'kg', 6.20, 9.80, 340, 600, 'Warehouse A - Bay 3', 'Injection-moulded PET preform, 28mm neck, for blow moulding.', NULL),
('BOX-MED-01', 'Carton Box - Medium', 6, 7, 'pcs', 2.40, 4.00, 95, 300, 'Warehouse E - Bay 1', 'Corrugated carton box, medium size, holds 24 bottles per box.', NULL);

-- Sample stock-in history (last ~6 months, for dashboard charts)
INSERT INTO stock_in (product_id, quantity, transaction_date, remarks, user_id, created_at) VALUES
(1, 2400, DATE(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'Regular monthly delivery', 2, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 1750, DATE(DATE_SUB(NOW(), INTERVAL 2 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9, 4000, DATE(DATE_SUB(NOW(), INTERVAL 3 DAY)), 'Urgent restock', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2100, DATE(DATE_SUB(NOW(), INTERVAL 6 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 5800, DATE(DATE_SUB(NOW(), INTERVAL 20 DAY)), 'July delivery', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 6200, DATE(DATE_SUB(NOW(), INTERVAL 25 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(7, 5500, DATE(DATE_SUB(NOW(), INTERVAL 35 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(1, 7100, DATE(DATE_SUB(NOW(), INTERVAL 50 DAY)), 'June delivery', 1, DATE_SUB(NOW(), INTERVAL 50 DAY)),
(9, 6830, DATE(DATE_SUB(NOW(), INTERVAL 55 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 55 DAY)),
(2, 5950, DATE(DATE_SUB(NOW(), INTERVAL 80 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 80 DAY)),
(1, 6240, DATE(DATE_SUB(NOW(), INTERVAL 85 DAY)), 'May delivery', 1, DATE_SUB(NOW(), INTERVAL 85 DAY)),
(7, 5920, DATE(DATE_SUB(NOW(), INTERVAL 110 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 110 DAY)),
(1, 5920, DATE(DATE_SUB(NOW(), INTERVAL 115 DAY)), 'April delivery', 2, DATE_SUB(NOW(), INTERVAL 115 DAY)),
(9, 6240, DATE(DATE_SUB(NOW(), INTERVAL 140 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 140 DAY)),
(1, 5820, DATE(DATE_SUB(NOW(), INTERVAL 145 DAY)), 'March delivery', 1, DATE_SUB(NOW(), INTERVAL 145 DAY));

-- Sample stock-out history (last ~6 months, for dashboard charts)
INSERT INTO stock_out (product_id, quantity, transaction_date, remarks, user_id, created_at) VALUES
(1, 1180, DATE(DATE_SUB(NOW(), INTERVAL 1 DAY)), 'Order #SO-2201', 2, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 950, DATE(DATE_SUB(NOW(), INTERVAL 2 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(16, 620, DATE(DATE_SUB(NOW(), INTERVAL 3 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 1750, DATE(DATE_SUB(NOW(), INTERVAL 7 DAY)), 'Order #SO-2189', 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 1450, DATE(DATE_SUB(NOW(), INTERVAL 21 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(2, 1350, DATE(DATE_SUB(NOW(), INTERVAL 26 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 26 DAY)),
(1, 1580, DATE(DATE_SUB(NOW(), INTERVAL 36 DAY)), 'July orders', 1, DATE_SUB(NOW(), INTERVAL 36 DAY)),
(7, 1470, DATE(DATE_SUB(NOW(), INTERVAL 51 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 51 DAY)),
(1, 1610, DATE(DATE_SUB(NOW(), INTERVAL 56 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 56 DAY)),
(2, 1310, DATE(DATE_SUB(NOW(), INTERVAL 81 DAY)), 'June orders', 1, DATE_SUB(NOW(), INTERVAL 81 DAY)),
(1, 1400, DATE(DATE_SUB(NOW(), INTERVAL 86 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 86 DAY)),
(7, 1290, DATE(DATE_SUB(NOW(), INTERVAL 111 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 111 DAY)),
(1, 1220, DATE(DATE_SUB(NOW(), INTERVAL 116 DAY)), 'May orders', 1, DATE_SUB(NOW(), INTERVAL 116 DAY)),
(2, 1240, DATE(DATE_SUB(NOW(), INTERVAL 141 DAY)), NULL, 2, DATE_SUB(NOW(), INTERVAL 141 DAY)),
(1, 1210, DATE(DATE_SUB(NOW(), INTERVAL 146 DAY)), NULL, 1, DATE_SUB(NOW(), INTERVAL 146 DAY));
