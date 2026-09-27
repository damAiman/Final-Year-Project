-- =====================================================================
-- SMART STOCK -- Demo 2 migration
-- Botol Anggun Sdn. Bhd.
-- ---------------------------------------------------------------------
-- Run this ONCE on an EXISTING Demo 1 database.
-- It EXTENDS the schema -- it does not drop or recreate anything, so all
-- your existing products and transactions are preserved.
--
-- phpMyAdmin -> select the `smart_stock` database -> Import -> this file.
-- =====================================================================

USE smart_stock;

-- ---------------------------------------------------------------------
-- 1. QR code payload on products
--    Botol Anggun already prints QR codes on its products. SMART STOCK
--    does not generate them -- it only reads them. This column stores the
--    text that the existing printed QR code contains, so a scan can be
--    matched back to a product row.
-- ---------------------------------------------------------------------
ALTER TABLE products
  ADD COLUMN qr_code VARCHAR(100) NULL AFTER sku;

-- Seed the QR payload from the existing SKU for all current products.
-- (In production the warehouse team would paste in whatever value their
--  printed labels actually encode.)
UPDATE products SET qr_code = sku WHERE qr_code IS NULL;

ALTER TABLE products
  ADD UNIQUE KEY uq_products_qr (qr_code);

-- ---------------------------------------------------------------------
-- 2. Record how a transaction was entered (typed vs scanned)
--    Lets the Reports module show how much of the workflow has moved to
--    QR scanning -- useful evidence for the FYP evaluation.
-- ---------------------------------------------------------------------
ALTER TABLE stock_in
  ADD COLUMN entry_method ENUM('manual','qr') NOT NULL DEFAULT 'manual' AFTER remarks;

ALTER TABLE stock_out
  ADD COLUMN entry_method ENUM('manual','qr') NOT NULL DEFAULT 'manual' AFTER remarks;

-- ---------------------------------------------------------------------
-- 3. Helpful indexes for the new Reports / Monitoring queries
-- ---------------------------------------------------------------------
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_supplier ON products(supplier_id);

-- =====================================================================
-- Done. Demo 2 modules will now work against your existing data.
-- =====================================================================
