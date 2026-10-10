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
('shop_phone','9011975959'),
('whatsapp_number','919011975959'),
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
CREATE UNIQUE INDEX IF NOT EXISTS users_google ON users (google_id);
CREATE INDEX IF NOT EXISTS orders_status_created ON orders (status, created_at);
CREATE INDEX IF NOT EXISTS orders_phone ON orders (customer_phone);
CREATE INDEX IF NOT EXISTS orders_reserved ON orders (reserved_until);
CREATE INDEX IF NOT EXISTS order_items_order ON order_items (order_id);
CREATE INDEX IF NOT EXISTS reviews_product_status ON reviews (product_id, status);
CREATE INDEX IF NOT EXISTS products_active_category ON products (active, category_id);

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


