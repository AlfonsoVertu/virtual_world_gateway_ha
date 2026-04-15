<?php
/**
 * Plugin Name: WWW Video Tickets
 * Description: Accesso a video HLS su CloudFront con licenze: X visioni in Y ore, AES-128 e cookie firmati.
 * Version: 1.0.0
 * Author: Working With Web
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

define('WWW_VT_VER', '1.0.0');
define('WWW_VT_DIR', plugin_dir_path(__FILE__));
define('WWW_VT_URL', plugin_dir_url(__FILE__));
define('WWW_VT_OPT', 'www_vt_options');
define('WWW_VT_DB',  'www_vt_licenses');
define('WWW_VT_DB_VER', '1.1');
define('WWW_VT_REST_NS', 'www-vt/v1');

require_once WWW_VT_DIR.'inc/helpers.php';
require_once WWW_VT_DIR.'inc/class-activator.php';
require_once WWW_VT_DIR.'inc/class-settings.php';
require_once WWW_VT_DIR.'inc/class-ads.php';
require_once WWW_VT_DIR.'inc/class-product-meta.php';
require_once WWW_VT_DIR.'inc/class-license-store.php';
require_once WWW_VT_DIR.'inc/class-cookie-signer.php';
require_once WWW_VT_DIR.'inc/class-jwt.php';
require_once WWW_VT_DIR.'inc/class-rest.php';
require_once WWW_VT_DIR.'inc/class-rest-ads.php';
require_once WWW_VT_DIR.'inc/class-rest-progress.php';
require_once WWW_VT_DIR.'inc/class-shortcode.php';
require_once WWW_VT_DIR.'inc/class-admin-licenses.php';
require_once WWW_VT_DIR.'inc/class-admin-ads-report.php';
require_once WWW_VT_DIR.'inc/class-keys-endpoint.php';

class WWW_Video_Tickets {
  public function __construct() {
    register_activation_hook(__FILE__, ['WWW_VT_Activator','activate']);
    add_action('plugins_loaded', [$this,'boot']);
  }

  public function boot() {
    if (function_exists('wp_cache_add_global_groups')) {
      wp_cache_add_global_groups(['www_vt']);
    }

    (new WWW_VT_Ads)->init();
    if (!class_exists('WooCommerce')) return;

    (new WWW_VT_Settings)->init();
    (new WWW_VT_Product_Meta)->init();
    (new WWW_VT_REST)->init();
    (new WWW_VT_Shortcode)->init();
    (new WWW_VT_Admin_Licenses)->init();
    (new WWW_VT_Admin_Ads_Report)->init();
    (new WWW_VT_Keys_Endpoint)->init();

    add_action('woocommerce_order_status_processing', [$this,'issue_licenses']);
    add_action('woocommerce_order_status_completed',  [$this,'issue_licenses']);
  }

  public function issue_licenses($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = (int)$order->get_user_id();
    if (!$user_id) return;

    foreach ($order->get_items('line_item') as $item) {
      $product_id = (int) $item->get_product_id();
      $max = (int) get_post_meta($product_id, '_www_vt_max_views', true);
      $win = (int) get_post_meta($product_id, '_www_vt_window_hours', true);
      $minutes = (int) get_post_meta($product_id, '_www_vt_total_minutes', true);
      $pre = www_vt_normalize_path_prefix((string) get_post_meta($product_id, '_www_vt_path_prefix', true));
      if ($max<=0 || $win<=0 || !$pre || $minutes<=0) continue;

      // Evita duplicati per stesso ordine/utente/prodotto
      if (WWW_VT_License_Store::exists_for_order_product($order_id, $user_id, $product_id)) continue;

      WWW_VT_License_Store::create([
        'user_id' => $user_id,
        'product_id' => $product_id,
        'order_id' => $order_id,
        'remaining_views' => $max,
        'remaining_seconds' => $minutes * 60,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + $win * 3600),
        'path_prefix' => $pre,
      ]);
    }
  }
}
new WWW_Video_Tickets();

function www_vt_maybe_upgrade_schema() {
  $current = get_option('www_vt_db_ver', '0');
  if (version_compare($current, WWW_VT_DB_VER, '<')) {
    WWW_VT_Activator::install_schema();
    update_option('www_vt_db_ver', WWW_VT_DB_VER);
  }
}
add_action('plugins_loaded', 'www_vt_maybe_upgrade_schema', 20);
