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
