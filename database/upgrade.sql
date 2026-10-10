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

