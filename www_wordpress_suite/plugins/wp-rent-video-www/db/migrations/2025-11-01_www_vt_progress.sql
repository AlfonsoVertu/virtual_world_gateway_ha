-- This migration detects the active WordPress table prefix automatically so it can run on staging and production without manual edits.
SET @schema := DATABASE();

SET @license_table := (
  SELECT table_name
  FROM information_schema.tables
  WHERE table_schema = @schema
    AND table_name LIKE '%\\_www\\_vt\\_licenses'
  ORDER BY (table_name = 'wp_www_vt_licenses') DESC, table_name ASC
  LIMIT 1
);

SET @license_table := IFNULL(@license_table, 'wp_www_vt_licenses');
SET @prefix := SUBSTRING_INDEX(@license_table, 'www_vt_licenses', 1);
SET @prefix := IF(@prefix = '', 'wp_', @prefix);
SET @license_table := CONCAT(@prefix, 'www_vt_licenses');
SET @progress_table := CONCAT(@prefix, 'www_vt_progress');

SET @license_exists := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = @schema
    AND table_name = @license_table
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema
    AND table_name = @license_table
    AND column_name = 'remaining_seconds'
);

SET @alter_sql := IF(
  @license_exists = 0,
  'DO 0;',
  IF(
    @col_exists = 0,
    CONCAT('ALTER TABLE `', @license_table, '` ADD COLUMN `remaining_seconds` INT UNSIGNED NOT NULL DEFAULT 0;'),
    'DO 0;'
  )
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @create_progress_sql := CONCAT(
  'CREATE TABLE IF NOT EXISTS `', @progress_table, '` (\n',
  '  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n',
  '  `license_id` BIGINT UNSIGNED NOT NULL,\n',
  '  `product_id` BIGINT UNSIGNED DEFAULT 0,\n',
  '  `user_id` BIGINT UNSIGNED DEFAULT 0,\n',
  '  `session_id` VARCHAR(191) NOT NULL,\n',
  '  `watched_seconds` INT UNSIGNED NOT NULL DEFAULT 0,\n',
  '  `position_seconds` INT UNSIGNED NOT NULL DEFAULT 0,\n',
  '  `complete` TINYINT(1) NOT NULL DEFAULT 0,\n',
  '  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n',
  '  PRIMARY KEY (`id`),\n',
  '  UNIQUE KEY `license_session` (`license_id`, `session_id`)\n',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
);

PREPARE stmt FROM @create_progress_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
