-- Run once in phpMyAdmin if the database was created from an older schema.
-- Safe to run again. Do not open this file in the browser.
SET NAMES utf8mb4;
DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;

CALL pa_add_column('users', 'status', 'ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''active''');
CALL pa_add_column('users', 'phone_verified_at', 'ALTER TABLE users ADD COLUMN phone_verified_at TIMESTAMP NULL');
CALL pa_add_column('users', 'last_login_at', 'ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL');
CALL pa_add_column('users', 'password_changed_at', 'ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL');
CALL pa_add_column('products', 'offer_starts_at', 'ALTER TABLE products ADD COLUMN offer_starts_at DATETIME NULL');
CALL pa_add_column('products', 'offer_ends_at', 'ALTER TABLE products ADD COLUMN offer_ends_at DATETIME NULL');
CALL pa_add_column('login_attempts', 'identifier', 'ALTER TABLE login_attempts ADD COLUMN identifier VARCHAR(160) NOT NULL DEFAULT ''''');
CALL pa_add_column('contact_messages', 'status', 'ALTER TABLE contact_messages ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''new''');
CALL pa_add_column('contact_messages', 'handled_at', 'ALTER TABLE contact_messages ADD COLUMN handled_at TIMESTAMP NULL');
CALL pa_add_column('contact_messages', 'staff_note', 'ALTER TABLE contact_messages ADD COLUMN staff_note VARCHAR(400) NULL');
CALL pa_add_column('wholesale_requests', 'status', 'ALTER TABLE wholesale_requests ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''new''');
CALL pa_add_column('wholesale_requests', 'staff_note', 'ALTER TABLE wholesale_requests ADD COLUMN staff_note VARCHAR(400) NULL');
DROP PROCEDURE IF EXISTS pa_add_column;

CREATE TABLE IF NOT EXISTS lookup_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY lookup_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mobile_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  provider_sid VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  verified_at TIMESTAMP NULL,
  ip VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED NULL,
  KEY mobile_verifications_phone (phone, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY password_resets_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS inventory_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NOT NULL,
  change_qty INT NOT NULL,
  stock_before INT NOT NULL,
  stock_after INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY inventory_movements_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admin_audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT UNSIGNED NOT NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  before_text VARCHAR(500) NULL,
  after_text VARCHAR(500) NULL,
  ip VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;

CALL pa_add_column('users', 'must_change_password', 'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('categories', 'active', 'ALTER TABLE categories ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1');
CALL pa_add_column('coupons', 'starts_at', 'ALTER TABLE coupons ADD COLUMN starts_at DATETIME NULL');
CALL pa_add_column('coupons', 'ends_at', 'ALTER TABLE coupons ADD COLUMN ends_at DATETIME NULL');
CALL pa_add_column('reviews', 'reply', 'ALTER TABLE reviews ADD COLUMN reply VARCHAR(600) NULL');
CALL pa_add_column('orders', 'internal_note', 'ALTER TABLE orders ADD COLUMN internal_note VARCHAR(500) NULL');
DROP PROCEDURE IF EXISTS pa_add_column;

ALTER TABLE settings MODIFY `value` TEXT NULL;
ALTER TABLE reviews MODIFY product_id INT UNSIGNED NULL;

INSERT INTO settings (`key`, `value`) VALUES
('min_order_inr','0'),
('shop_phone','8080370152'),
('whatsapp_number','918080370152'),
('shop_email','info@precisionagritech.in'),
('mail_from','info@precisionagritech.in'),
('shop_address','Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110'),
('opening_hours','Monday to Saturday, 9:00 to 6:00'),
('map_url','https://www.google.com/maps/search/?api=1&query=Survey+No.+44/2+Theur+Naygaon+Road+Gaikwadvasti+Pune+412110'),
('payment_instructions','The nursery confirms bank transfer before the trays leave. Cash on delivery is collected when the trays are delivered.'),
('announcement',''),
('announcement_on','0'),
('shop_paused','0')
ON DUPLICATE KEY UPDATE `key` = `key`;

DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;

CALL pa_add_column('users', 'google_id', 'ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL');
CALL pa_add_column('users', 'email_verified_at', 'ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL');
CALL pa_add_column('users', 'deletion_requested', 'ALTER TABLE users ADD COLUMN deletion_requested TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'reserved_until', 'ALTER TABLE orders ADD COLUMN reserved_until DATETIME NULL');
CALL pa_add_column('orders', 'payment_ref', 'ALTER TABLE orders ADD COLUMN payment_ref VARCHAR(40) NULL');
CALL pa_add_column('orders', 'payment_note', 'ALTER TABLE orders ADD COLUMN payment_note VARCHAR(255) NULL');
CALL pa_add_column('orders', 'paid_amount_inr', 'ALTER TABLE orders ADD COLUMN paid_amount_inr INT NULL');
CALL pa_add_column('orders', 'paid_on', 'ALTER TABLE orders ADD COLUMN paid_on DATE NULL');
CALL pa_add_column('orders', 'paid_by', 'ALTER TABLE orders ADD COLUMN paid_by INT UNSIGNED NULL');
CALL pa_add_column('orders', 'driver_id', 'ALTER TABLE orders ADD COLUMN driver_id INT UNSIGNED NULL');
CALL pa_add_column('orders', 'delivery_date', 'ALTER TABLE orders ADD COLUMN delivery_date DATE NULL');
CALL pa_add_column('orders', 'delivery_slot', 'ALTER TABLE orders ADD COLUMN delivery_slot VARCHAR(20) NULL');
CALL pa_add_column('orders', 'phone_confirmed', 'ALTER TABLE orders ADD COLUMN phone_confirmed TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'cash_collected_inr', 'ALTER TABLE orders ADD COLUMN cash_collected_inr INT NULL');
CALL pa_add_column('orders', 'cash_handed_at', 'ALTER TABLE orders ADD COLUMN cash_handed_at DATETIME NULL');
CALL pa_add_column('orders', 'collected_by', 'ALTER TABLE orders ADD COLUMN collected_by INT UNSIGNED NULL');
CALL pa_add_column('orders', 'collected_at', 'ALTER TABLE orders ADD COLUMN collected_at DATETIME NULL');
CALL pa_add_column('orders', 'cancel_reason', 'ALTER TABLE orders ADD COLUMN cancel_reason VARCHAR(255) NULL');
DROP PROCEDURE IF EXISTS pa_add_column;

ALTER TABLE users MODIFY email VARCHAR(160) NULL;
ALTER TABLE users MODIFY phone VARCHAR(20) NULL;
ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL;
ALTER TABLE orders MODIFY customer_email VARCHAR(160) NULL;

-- Index helper. CREATE INDEX IF NOT EXISTS is MariaDB-only.
-- This checks information_schema and works on MariaDB, MySQL 5.7 and MySQL 8.
DROP PROCEDURE IF EXISTS pa_add_index;
DELIMITER $$
CREATE PROCEDURE pa_add_index(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;

CALL pa_add_index('users', 'users_google', 'ALTER TABLE users ADD UNIQUE KEY users_google (google_id)');
CALL pa_add_index('orders', 'orders_status_created', 'ALTER TABLE orders ADD KEY orders_status_created (status, created_at)');
CALL pa_add_index('orders', 'orders_phone', 'ALTER TABLE orders ADD KEY orders_phone (customer_phone)');
CALL pa_add_index('orders', 'orders_reserved', 'ALTER TABLE orders ADD KEY orders_reserved (reserved_until)');
CALL pa_add_index('order_items', 'order_items_order', 'ALTER TABLE order_items ADD KEY order_items_order (order_id)');
CALL pa_add_index('reviews', 'reviews_product_status', 'ALTER TABLE reviews ADD KEY reviews_product_status (product_id, status)');
CALL pa_add_index('products', 'products_active_category', 'ALTER TABLE products ADD KEY products_active_category (active, category_id)');

CREATE TABLE IF NOT EXISTS drivers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  vehicle_no VARCHAR(20) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cod_blocks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  note VARCHAR(200) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY cod_blocks_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS claims (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  photos VARCHAR(800) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  staff_reply VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY claims_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS refunds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  amount_inr INT NOT NULL,
  method VARCHAR(40) NOT NULL,
  paid_on DATE NOT NULL,
  admin_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS email_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  salt CHAR(16) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  ip VARCHAR(64) NOT NULL,
  KEY email_codes_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS order_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY order_attempts_ip (ip, created_at),
  KEY order_attempts_phone (phone, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE orders
SET reserved_until = DATE_ADD(created_at, INTERVAL 48 HOUR)
WHERE status IN ('placed', 'payment_pending')
  AND inventory_state = 'reserved'
  AND reserved_until IS NULL;

INSERT INTO settings (`key`, `value`) VALUES
('reservation_hours_bank','48'),
('reservation_hours_cod','72'),
('require_email','1'),
('cod_enabled','1'),
('require_phone_confirm','1'),
('upi_id','precision9958@fbl'),
('upi_payee_name','Precision Agritech Private Limited'),
('seller_legal_name','Precision Agritech Private Limited'),
('bank_account_name',''),
('bank_name',''),
('bank_account_number',''),
('bank_ifsc',''),
('upi_qr_image',''),
('gstin',''),
('cin',''),
('grievance_officer_name',''),
('grievance_officer_phone',''),
('grievance_officer_email',''),
('delivery_pins',''),
('delivery_area_text','We deliver with our own nursery vehicles.'),
('zone_shipping',''),
('cod_min_inr','0'),
('cod_max_inr','0'),
('show_driver_phone','0'),
('claim_hours','48'),
('hsn_code',''),
('gst_rate',''),
('cleanup_last_run','0')
ON DUPLICATE KEY UPDATE `key` = `key`;

DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;

CALL pa_add_column('orders', 'cash_reason', 'ALTER TABLE orders ADD COLUMN cash_reason VARCHAR(160) NULL');
CALL pa_add_column('orders', 'reservation_extended', 'ALTER TABLE orders ADD COLUMN reservation_extended TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'reminder_sent', 'ALTER TABLE orders ADD COLUMN reminder_sent TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'payment_alerted', 'ALTER TABLE orders ADD COLUMN payment_alerted TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('coupons', 'per_customer_limit', 'ALTER TABLE coupons ADD COLUMN per_customer_limit INT NOT NULL DEFAULT 1');
CALL pa_add_column('coupon_redemptions', 'phone', 'ALTER TABLE coupon_redemptions ADD COLUMN phone VARCHAR(20) NULL');
CALL pa_add_column('refunds', 'utr', 'ALTER TABLE refunds ADD COLUMN utr VARCHAR(80) NULL');
CALL pa_add_column('users', 'desk_role', 'ALTER TABLE users ADD COLUMN desk_role VARCHAR(20) NOT NULL DEFAULT ''owner''');
DROP PROCEDURE IF EXISTS pa_add_column;

DROP PROCEDURE IF EXISTS pa_add_index;
DELIMITER $$
CREATE PROCEDURE pa_add_index(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;

CALL pa_add_index('email_codes', 'email_codes_lookup', 'ALTER TABLE email_codes ADD KEY email_codes_lookup (email, purpose, expires_at)');
CALL pa_add_index('orders', 'orders_payment_ref', 'ALTER TABLE orders ADD KEY orders_payment_ref (payment_ref)');
CALL pa_add_index('orders', 'orders_driver_date', 'ALTER TABLE orders ADD KEY orders_driver_date (driver_id, delivery_date)');
CALL pa_add_index('orders', 'orders_status_reserved', 'ALTER TABLE orders ADD KEY orders_status_reserved (status, reserved_until)');
CALL pa_add_index('claims', 'claims_status', 'ALTER TABLE claims ADD KEY claims_status (status)');
CALL pa_add_index('refunds', 'refunds_order', 'ALTER TABLE refunds ADD KEY refunds_order (order_id)');
CALL pa_add_index('coupon_redemptions', 'coupon_redemptions_email', 'ALTER TABLE coupon_redemptions ADD KEY coupon_redemptions_email (coupon_id, customer_email)');
CALL pa_add_index('coupon_redemptions', 'coupon_redemptions_phone', 'ALTER TABLE coupon_redemptions ADD KEY coupon_redemptions_phone (coupon_id, phone)');
DROP PROCEDURE IF EXISTS pa_add_index;

CREATE TABLE IF NOT EXISTS order_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  kind VARCHAR(30) NOT NULL,
  path VARCHAR(255) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY order_files_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mail_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(160) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  ok TINYINT(1) NOT NULL,
  error VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY mail_log_created (created_at, ok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES
('max_unpaid_phone','2'),
('max_unpaid_ip','3'),
('max_trays_phone','60'),
('cod_fail_limit','2'),
('cod_max_inr','5000'),
('reservation_hours_bank','24')
ON DUPLICATE KEY UPDATE `key` = `key`;

UPDATE settings SET `value` = '24' WHERE `key` = 'reservation_hours_bank' AND `value` = '48';
UPDATE settings SET `value` = '5000' WHERE `key` = 'cod_max_inr' AND `value` = '0';

INSERT INTO settings (`key`, `value`) VALUES
('upi_qr_mode','auto'),
('upi_enabled','1'),
('bank_enabled','1'),
('hero_kicker','Precision Agritech · Theur, Pune'),
('hero_title','Healthy seedlings. Better blooms.'),
('hero_text','Nursery-grown flower seedlings from Theur, Pune. Sold by the tray.'),
('hero_primary_label','Shop seedlings'),
('hero_primary_href','/shop'),
('hero_secondary_label','Explore our nursery'),
('hero_secondary_href','/nursery'),
('rail_best_title','Best sellers'),
('rail_new_title','New arrivals'),
('rail_offers_title','Offers'),
('rail_best_on','1'),
('rail_new_on','1'),
('rail_offers_on','1'),
('rail_limit','8'),
('low_stock_threshold','5')
ON DUPLICATE KEY UPDATE `key` = `key`;

DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;
CALL pa_add_column('users', 'farmer', 'ALTER TABLE users ADD COLUMN farmer TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('products', 'seedlings_override', 'ALTER TABLE products ADD COLUMN seedlings_override INT NULL');
CALL pa_add_column('products', 'price_per_seedling_paise', 'ALTER TABLE products ADD COLUMN price_per_seedling_paise INT NULL');
CALL pa_add_column('products', 'farmer_only', 'ALTER TABLE products ADD COLUMN farmer_only TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('products', 'is_overgrown', 'ALTER TABLE products ADD COLUMN is_overgrown TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'seasonal_discount_inr', 'ALTER TABLE orders ADD COLUMN seasonal_discount_inr INT NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'price_list_name', 'ALTER TABLE orders ADD COLUMN price_list_name VARCHAR(80) NULL');
CALL pa_add_column('order_items', 'seedling_paise', 'ALTER TABLE order_items ADD COLUMN seedling_paise INT NULL');
CALL pa_add_column('order_items', 'tray_size', 'ALTER TABLE order_items ADD COLUMN tray_size INT NULL');
CALL pa_add_column('order_items', 'price_list_name', 'ALTER TABLE order_items ADD COLUMN price_list_name VARCHAR(80) NULL');
CALL pa_add_column('order_items', 'marathi_name', 'ALTER TABLE order_items ADD COLUMN marathi_name VARCHAR(160) NULL');
DROP PROCEDURE IF EXISTS pa_add_column;

CREATE TABLE IF NOT EXISTS price_lists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  audience VARCHAR(20) NOT NULL,
  effective_from DATE NULL,
  effective_to DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(500) NOT NULL DEFAULT '',
  source_ref VARCHAR(160) NOT NULL DEFAULT '',
  UNIQUE KEY price_lists_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO price_lists (slug, name, audience, notes, source_ref, active)
SELECT 'seasonal-2026-27', 'Seasonal (2026-27)', 'seasonal', 'Standard rates for the 2026-27 season.', 'DOC-20260923-WA0002_.pdf', 1
WHERE NOT EXISTS (SELECT 1 FROM price_lists WHERE slug = 'seasonal-2026-27');
INSERT INTO price_lists (slug, name, audience, effective_from, notes, source_ref, active)
SELECT 'farmer', 'Farmer', 'farmer', '2026-01-01', 'Farmer rates. Marathi names stay as printed.', 'IMG20261012141209.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM price_lists WHERE slug = 'farmer');
CREATE TABLE IF NOT EXISTS price_list_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  price_list_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  sku VARCHAR(40) NOT NULL,
  item_name VARCHAR(120) NOT NULL,
  variety VARCHAR(160) NOT NULL DEFAULT '',
  marathi_name VARCHAR(160) NOT NULL DEFAULT '',
  price_per_seedling_paise INT NOT NULL,
  seedlings_per_tray INT NULL,
  visibility VARCHAR(20) NOT NULL DEFAULT 'everyone',
  active TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY price_items_sku (price_list_id, sku),
  KEY price_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoice_counters (
  fy VARCHAR(9) NOT NULL PRIMARY KEY,
  last_no INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(40) NOT NULL,
  fy VARCHAR(9) NOT NULL,
  total_inr INT NOT NULL,
  snapshot_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY invoices_number (invoice_number),
  UNIQUE KEY invoices_order (order_id),
  KEY invoices_fy (fy, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS credit_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  invoice_id INT UNSIGNED NULL,
  credit_number VARCHAR(40) NOT NULL,
  fy VARCHAR(9) NOT NULL,
  amount_inr INT NOT NULL,
  snapshot_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY credit_notes_number (credit_number),
  KEY credit_notes_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO settings (`key`, `value`) VALUES
('seedlings_per_tray','150'),
('show_seedling_count','0'),
('seasonal_discount_on','0'),
('seasonal_discount_percent','10'),
('seasonal_discount_min_inr','5000'),
('seasonal_discount_label','Seasonal discount'),
('seasonal_discount_start',''),
('seasonal_discount_end',''),
('seasonal_stacks_coupons','0'),
('seasonal_applies_farmer','0'),
('seasonal_applies_cod','1'),
('seasonal_applies_overgrown','0'),
('farmer_fallback_seasonal','0'),
('container_addon_on','0'),
('container_addon_paise','50'),
('invoice_prefix','PA'),
('gst_enabled','0'),
('overgrown_nav','0'),
('invoice_email_on','1'),
('overgrown_intro','These trays grew past the usual transplant stage. They are sold as they are.'),
('prices_dispatch_note','Prices are subject to change. The price at dispatch is final.'),
('invoice_terms','')
ON DUPLICATE KEY UPDATE `key` = `key`;




-- 2026-10-12 lists, phones, banks. Portable: procedure calls are applied by includes/migrate.php.
DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;
CALL pa_add_column('products', 'parent_product_id', 'ALTER TABLE products ADD COLUMN parent_product_id INT UNSIGNED NULL');
CALL pa_add_column('products', 'tray_paise', 'ALTER TABLE products ADD COLUMN tray_paise INT NULL');
CALL pa_add_column('products', 'marathi_name', 'ALTER TABLE products ADD COLUMN marathi_name VARCHAR(160) NOT NULL DEFAULT ''''');
CALL pa_add_column('products', 'needs_copy', 'ALTER TABLE products ADD COLUMN needs_copy TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('orders', 'subtotal_paise', 'ALTER TABLE orders ADD COLUMN subtotal_paise INT NULL');
CALL pa_add_column('orders', 'discount_paise', 'ALTER TABLE orders ADD COLUMN discount_paise INT NULL');
CALL pa_add_column('orders', 'shipping_paise', 'ALTER TABLE orders ADD COLUMN shipping_paise INT NULL');
CALL pa_add_column('orders', 'total_paise', 'ALTER TABLE orders ADD COLUMN total_paise INT NULL');
CALL pa_add_column('orders', 'seasonal_discount_paise', 'ALTER TABLE orders ADD COLUMN seasonal_discount_paise INT NULL');
CALL pa_add_column('orders', 'transport_paise', 'ALTER TABLE orders ADD COLUMN transport_paise INT NOT NULL DEFAULT 0');
CALL pa_add_column('order_items', 'unit_paise', 'ALTER TABLE order_items ADD COLUMN unit_paise INT NULL');
CALL pa_add_column('order_items', 'line_paise', 'ALTER TABLE order_items ADD COLUMN line_paise INT NULL');
DROP PROCEDURE IF EXISTS pa_add_column;
DROP PROCEDURE IF EXISTS pa_add_index;
DELIMITER $$
CREATE PROCEDURE pa_add_index(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;
CALL pa_add_index('products', 'products_parent', 'ALTER TABLE products ADD KEY products_parent (parent_product_id)');
DROP PROCEDURE IF EXISTS pa_add_index;
CREATE TABLE IF NOT EXISTS phone_numbers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  digits VARCHAR(10) NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  show_public TINYINT(1) NOT NULL DEFAULT 1,
  whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY phone_numbers_digits (digits)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO phone_numbers (digits, label, sort_order, enabled, show_public, whatsapp_enabled)
SELECT '8080370152', 'Nursery', 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM phone_numbers WHERE digits = '8080370152');
INSERT INTO phone_numbers (digits, label, sort_order, enabled, show_public, whatsapp_enabled)
SELECT '8421577787', 'Office', 2, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM phone_numbers WHERE digits = '8421577787');
INSERT INTO phone_numbers (digits, label, sort_order, enabled, show_public, whatsapp_enabled)
SELECT '8421577725', 'Desk', 3, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM phone_numbers WHERE digits = '8421577725');
CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bank VARCHAR(80) NOT NULL,
  branch VARCHAR(80) NOT NULL DEFAULT '',
  account_name VARCHAR(120) NOT NULL,
  account_no VARCHAR(30) NOT NULL,
  ifsc VARCHAR(20) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  show_public TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY bank_accounts_no (account_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO bank_accounts (bank, branch, account_name, account_no, ifsc, sort_order, verified, show_public)
SELECT 'AXIS BANK LTD', 'Hadapsar Branch', 'Precision Agritech Pvt. Ltd.', '913020018359580', 'UTIB0003185', 1, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM bank_accounts WHERE account_no = '913020018359580');
INSERT INTO bank_accounts (bank, branch, account_name, account_no, ifsc, sort_order, verified, show_public)
SELECT 'BANK OF INDIA', 'Theur Branch', 'Precision Agritech Pvt. Ltd.', '060720110000151', 'BKID0000607', 2, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM bank_accounts WHERE account_no = '060720110000151');
INSERT INTO bank_accounts (bank, branch, account_name, account_no, ifsc, sort_order, verified, show_public)
SELECT 'FEDERAL BANK', 'Pune Branch', 'Precision Agritech Pvt. Ltd.', '13480200009958', 'FDRL0001348', 3, 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM bank_accounts WHERE account_no = '13480200009958');
UPDATE settings SET `value` = '8080370152' WHERE `key` = 'shop_phone' AND `value` NOT IN ('8080370152','8421577787','8421577725');
UPDATE settings SET `value` = '918080370152' WHERE `key` = 'whatsapp_number' AND `value` NOT IN ('918080370152','918421577787','918421577725');
UPDATE settings SET `value` = '8080370152' WHERE `key` = 'grievance_officer_phone' AND `value` NOT IN ('8080370152','8421577787','8421577725');
DELETE FROM phone_numbers WHERE digits NOT IN ('8080370152','8421577787','8421577725');
INSERT INTO settings (`key`, `value`) VALUES
('whatsapp_mode','random'),
('whatsapp_fixed_id','0'),
('whatsapp_rr','0'),
('seasonal_list_public','1'),
('show_marathi_names','1'),
('shop_tagline','Premium Flower & Ornamental Seedling Price List 2026-27'),
('footer_line','Thank you for your trust in Precision Agritech - Growing Quality, Nurturing Nature.')
ON DUPLICATE KEY UPDATE `key` = `key`;
UPDATE price_lists SET effective_from = '2026-04-01', effective_to = '2027-03-31' WHERE slug = 'seasonal-2026-27' AND effective_from IS NULL;
UPDATE price_lists SET effective_from = '2026-01-01' WHERE slug = 'farmer' AND effective_from IS NULL;
DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;
CALL pa_add_column('products', 'image_is_placeholder', 'ALTER TABLE products ADD COLUMN image_is_placeholder TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('products', 'purposes', 'ALTER TABLE products ADD COLUMN purposes VARCHAR(240) NOT NULL DEFAULT ''''');
CALL pa_add_column('products', 'list_only', 'ALTER TABLE products ADD COLUMN list_only TINYINT(1) NOT NULL DEFAULT 0');
DROP PROCEDURE IF EXISTS pa_add_column;
CREATE TABLE IF NOT EXISTS restock_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  contact VARCHAR(160) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY restock_product (product_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS price_undo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  old_paise INT NOT NULL,
  new_paise INT NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  undone TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY price_undo_product (product_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO settings (`key`, `value`) VALUES
('discount_basis','rupees'),
('discount_min_seedlings','5000'),
('discount_banner',''),
('default_starting_stock','40'),
('schema_version',''),
('lists_loaded','0')
ON DUPLICATE KEY UPDATE `key` = `key`;
UPDATE settings SET `value` = '' WHERE `key` = 'invoice_terms' AND CHAR_LENGTH(`value`) > 180;
DROP PROCEDURE IF EXISTS pa_add_column;
DELIMITER $$
CREATE PROCEDURE pa_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @pa_ddl = ddl;
    PREPARE pa_stmt FROM @pa_ddl;
    EXECUTE pa_stmt;
    DEALLOCATE PREPARE pa_stmt;
  END IF;
END $$
DELIMITER ;
CALL pa_add_column('products', 'offer_seedling_paise', 'ALTER TABLE products ADD COLUMN offer_seedling_paise INT NULL');
DROP PROCEDURE IF EXISTS pa_add_column;
INSERT INTO settings (`key`, `value`) VALUES
('calc_menu_on','1'),
('browse_on','1'),
('browse_title','Browse all plants'),
('browse_chip_order',''),
('offer_allows_bulk','1'),
('nursery_min_seedling_paise','100'),
('home_sections',''),
('calc_label_en',''),
('calc_label_mr','')
ON DUPLICATE KEY UPDATE `key` = `key`;

