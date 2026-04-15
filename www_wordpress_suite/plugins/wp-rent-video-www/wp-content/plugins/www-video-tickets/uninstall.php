<?php
// Disinstalla: rimuove tabella licenze se confermato.
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;
$table = $wpdb->prefix . 'www_vt_licenses';

// Cancella tabella solo se l'admin ha impostato una costante esplicita
if (defined('WWW_VT_DELETE_DATA') && WWW_VT_DELETE_DATA === true) {
  $wpdb->query("DROP TABLE IF EXISTS `$table`");
  $progress = $wpdb->prefix . 'www_vt_progress';
  $ads = $wpdb->prefix . 'www_vt_ads_views';
  $wpdb->query("DROP TABLE IF EXISTS `$progress`");
  $wpdb->query("DROP TABLE IF EXISTS `$ads`");

  $ads_posts = get_posts([
    'post_type'      => 'www_vt_ad',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ]);
  foreach ($ads_posts as $ad_post_id) {
    wp_delete_post($ad_post_id, true);
  }
}

// Cancella opzioni
delete_option('www_vt_options');
