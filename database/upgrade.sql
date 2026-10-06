-- Safe to run more than once on a database that already exists.
-- Do not open this file in the browser.
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
CALL pa_add_column('users', 'email_verified_at', 'ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL');
CALL pa_add_column('users', 'last_login_at', 'ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL');
CALL pa_add_column('users', 'password_changed_at', 'ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL');
CALL pa_add_column('addresses', 'is_default', 'ALTER TABLE addresses ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('products', 'offer_starts_at', 'ALTER TABLE products ADD COLUMN offer_starts_at DATETIME NULL');
CALL pa_add_column('products', 'offer_ends_at', 'ALTER TABLE products ADD COLUMN offer_ends_at DATETIME NULL');
CALL pa_add_column('products', 'use_tags', 'ALTER TABLE products ADD COLUMN use_tags VARCHAR(200) NOT NULL DEFAULT ''''');
CALL pa_add_column('products', 'sale_price_inr', 'ALTER TABLE products ADD COLUMN sale_price_inr INT NULL');
CALL pa_add_column('orders', 'reserved_until', 'ALTER TABLE orders ADD COLUMN reserved_until DATETIME NULL');
CALL pa_add_column('orders', 'cancel_reason', 'ALTER TABLE orders ADD COLUMN cancel_reason VARCHAR(255) NULL');
CALL pa_add_column('orders', 'cancel_requested', 'ALTER TABLE orders ADD COLUMN cancel_requested TINYINT(1) NOT NULL DEFAULT 0');
CALL pa_add_column('order_events', 'public_note', 'ALTER TABLE order_events ADD COLUMN public_note TINYINT(1) NOT NULL DEFAULT 1');
CALL pa_add_column('coupons', 'per_customer_limit', 'ALTER TABLE coupons ADD COLUMN per_customer_limit INT NULL');
CALL pa_add_column('coupons', 'expires_at', 'ALTER TABLE coupons ADD COLUMN expires_at DATETIME NULL');
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
  UNIQUE KEY password_resets_hash (token_hash),
  CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
CREATE TABLE IF NOT EXISTS carts (
  user_id INT UNSIGNED NOT NULL,
  slug VARCHAR(80) NOT NULL,
  qty INT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS order_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY order_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS email_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY email_tokens_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS slug_redirects (
  from_slug VARCHAR(80) NOT NULL PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS stock_alerts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(20) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(40) NOT NULL PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES
('bank_account_name',''),
('bank_name',''),
('bank_account_number',''),
('bank_ifsc',''),
('upi_id',''),
('cod_reserve_hours','72'),
('bank_reserve_hours','48'),
('contact_phone','9011975959'),
('contact_email','info@precisionagritech.in'),
('whatsapp_number','919011975959'),
('business_hours','9:00 to 6:00, Monday to Saturday')
ON DUPLICATE KEY UPDATE `key` = `key`;

UPDATE products SET sale_price_inr = price_inr, price_inr = compare_at_inr
WHERE sale_price_inr IS NULL AND compare_at_inr IS NOT NULL AND compare_at_inr > price_inr AND on_offer = 1;
UPDATE products SET use_tags = CASE slug
 WHEN 'marigold' THEN 'beds,borders,garlands'
 WHEN 'petunia' THEN 'beds,pots,borders'
 WHEN 'vinca' THEN 'beds,borders,pots,landscaping'
 WHEN 'pansy' THEN 'beds,pots,borders'
 WHEN 'geranium' THEN 'pots,landscaping'
 WHEN 'zinnia' THEN 'beds'
 WHEN 'celosia' THEN 'beds,landscaping'
 WHEN 'sunflower' THEN 'beds,landscaping'
 WHEN 'antirrhinum' THEN 'beds'
 WHEN 'dianthus' THEN 'pots,borders'
 WHEN 'cineraria' THEN 'pots'
 WHEN 'lobelia' THEN 'pots,borders'
 WHEN 'gazania' THEN 'beds,pots,landscaping'
 WHEN 'salvia' THEN 'beds,landscaping'
 WHEN 'begonia' THEN 'beds,pots,landscaping'
 WHEN 'impatiens' THEN 'beds'
 WHEN 'chrysanthemum' THEN 'beds,pots'
 WHEN 'pentas' THEN 'beds,landscaping'
 WHEN 'platycodon' THEN 'beds,pots'
 WHEN 'ptilotus' THEN 'beds,pots,landscaping'
 ELSE use_tags END
WHERE use_tags = '';

UPDATE products p
JOIN order_items oi ON oi.product_id = p.id
JOIN orders o ON o.id = oi.order_id
SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity),
    p.stock_qty = GREATEST(0, p.stock_qty - oi.quantity)
WHERE o.status = 'delivered' AND o.inventory_state = 'reserved';
UPDATE orders SET inventory_state = 'sold', payment_status = 'paid'
WHERE status = 'delivered' AND inventory_state = 'reserved';

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026-10-06-shop');
