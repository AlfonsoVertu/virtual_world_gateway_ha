<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Activator {
  public static function activate() {
    self::install_schema();
    if (defined('WWW_VT_DB_VER')) {
      update_option('www_vt_db_ver', WWW_VT_DB_VER);
    }
    flush_rewrite_rules();
  }

  public static function install_schema() {
    global $wpdb;
    $table = $wpdb->prefix . WWW_VT_DB;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      product_id BIGINT UNSIGNED NOT NULL,
      order_id BIGINT UNSIGNED NOT NULL,
      remaining_views INT NOT NULL DEFAULT 0,
      remaining_seconds INT UNSIGNED NOT NULL DEFAULT 0,
      expires_at DATETIME NOT NULL,
      path_prefix VARCHAR(255) NOT NULL,
      consumed_sessions LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY user_product (user_id, product_id),
      KEY expires_at (expires_at),
      KEY order_user_product (order_id, user_id, product_id)
    ) $charset;";
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $progress = $wpdb->prefix . 'www_vt_progress';
    $sql_progress = "CREATE TABLE IF NOT EXISTS `$progress` (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      license_id BIGINT UNSIGNED NOT NULL,
      product_id BIGINT UNSIGNED DEFAULT 0,
      user_id BIGINT UNSIGNED DEFAULT 0,
      session_id VARCHAR(191) NOT NULL,
      watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
      position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
      complete TINYINT(1) NOT NULL DEFAULT 0,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY license_session (license_id, session_id)
    ) $charset;";
    dbDelta($sql_progress);

    $ads_views = $wpdb->prefix . 'www_vt_ads_views';
    $sql_ads = "CREATE TABLE IF NOT EXISTS `$ads_views` (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      ad_id BIGINT UNSIGNED NOT NULL,
      product_id BIGINT UNSIGNED DEFAULT 0,
      license_id BIGINT UNSIGNED DEFAULT 0,
      user_id BIGINT UNSIGNED DEFAULT 0,
      session_id VARCHAR(191) NOT NULL,
      viewed_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY ad_session (ad_id, session_id),
      KEY ad_product (ad_id, product_id),
      KEY license_idx (license_id),
      KEY session_idx (session_id)
    ) $charset;";
    dbDelta($sql_ads);
  }
}
