-- =====================================================================
-- SMART STOCK -- Demo 3 migration
-- Botol Anggun Sdn. Bhd.
-- ---------------------------------------------------------------------
-- Run this ONCE on an EXISTING Demo 2 database.
-- It EXTENDS the schema -- nothing is dropped or recreated, so all your
-- existing users, products and transactions are preserved.
--
-- phpMyAdmin -> select the `smart_stock` database -> Import -> this file.
-- =====================================================================

USE smart_stock;

-- ---------------------------------------------------------------------
-- 1. User Management: activate / deactivate + audit trail on the account
-- ---------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD COLUMN email VARCHAR(120) NULL AFTER name,
  ADD COLUMN last_login DATETIME NULL AFTER is_active,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Give the two seeded demo accounts an email so the UI has something to show.
UPDATE users SET email = CONCAT(username, '@botolanggun.com.my') WHERE email IS NULL;

-- ---------------------------------------------------------------------
-- 2. Activity Log -- "who did what, to which product, how much"
--    Written automatically by Stock In / Stock Out / Product CRUD.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  user_name VARCHAR(100) NOT NULL,      -- denormalised so history survives user deletion
  module VARCHAR(50) NOT NULL,          -- Dashboard, Product Management, Stock In, ...
  activity VARCHAR(255) NOT NULL,       -- human readable sentence
  product_id INT NULL,
  product_name VARCHAR(150) NULL,       -- denormalised for the same reason
  quantity INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_activity_created ON activity_log(created_at);
CREATE INDEX idx_activity_module  ON activity_log(module);

-- ---------------------------------------------------------------------
-- 3. Audit Log -- "what value changed, from what, to what"
--    Written automatically whenever a record is created/updated/deleted.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  user_name VARCHAR(100) NOT NULL,
  module VARCHAR(50) NOT NULL,
  action VARCHAR(50) NOT NULL,          -- CREATE / UPDATE / DELETE / LOGIN / RESTORE ...
  record_type VARCHAR(50) NULL,         -- Product, User, Setting, ...
  record_id INT NULL,
  field_name VARCHAR(80) NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_audit_created ON audit_log(created_at);
CREATE INDEX idx_audit_module  ON audit_log(module);
CREATE INDEX idx_audit_action  ON audit_log(action);

-- ---------------------------------------------------------------------
-- 4. Notification centre
--    user_id NULL = broadcast (everyone sees it, e.g. low stock warnings)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  type VARCHAR(30) NOT NULL,            -- low_stock | stock_in | stock_out | report | user | system
  title VARCHAR(150) NOT NULL,
  message VARCHAR(255) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notif_created ON notifications(created_at);
CREATE INDEX idx_notif_read    ON notifications(is_read);

-- ---------------------------------------------------------------------
-- 5. System settings -- simple key/value store
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('company_name',        'Botol Anggun Sdn. Bhd.'),
  ('company_reg_no',      '201501012345 (1234567-A)'),
  ('company_address',     'Kajang, Selangor'),
  ('company_phone',       '+60 3-0000 0000'),
  ('company_email',       'admin@botolanggun.com.my'),
  ('company_logo',        ''),
  ('language',            'en'),
  ('dark_mode',           '0'),
  ('notify_low_stock',    '1'),
  ('notify_stock_in',     '1'),
  ('notify_stock_out',    '1'),
  ('notify_reports',      '1'),
  ('notify_users',        '1'),
  ('session_timeout_min', '30')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================================
-- Done. Demo 3 modules will now work against your existing data.
-- =====================================================================
