<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_License_Store {
  public static function create(array $row) {
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB; $now=gmdate('Y-m-d H:i:s');
    $path = www_vt_normalize_path_prefix((string)$row['path_prefix']);
    $wpdb->insert($t, [
      'user_id' => (int)$row['user_id'],
      'product_id' => (int)$row['product_id'],
      'order_id' => (int)$row['order_id'],
      'remaining_views' => (int)$row['remaining_views'],
      'remaining_seconds' => max(0, (int)($row['remaining_seconds'] ?? 0)),
      'expires_at' => $row['expires_at'],
      'path_prefix' => sanitize_text_field($path),
      'consumed_sessions' => wp_json_encode([]),
      'created_at' => $now, 'updated_at' => $now
    ]);
    return $wpdb->insert_id;
  }

  public static function exists_for_order_product($order_id,$user_id,$product_id): bool {
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB;
    $id = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $t WHERE order_id=%d AND user_id=%d AND product_id=%d LIMIT 1",
      $order_id, $user_id, $product_id
    ));
    return (bool)$id;
  }

  public static function get_active_for_user_product($user_id,$product_id) {
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB; $now=gmdate('Y-m-d H:i:s');
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $t WHERE user_id=%d AND product_id=%d AND expires_at>%s AND (remaining_seconds>0 OR remaining_views>0) ORDER BY id DESC LIMIT 1",
      $user_id,$product_id,$now
    ), ARRAY_A);
  }

  public static function consume_if_needed($license_id,$session_id) {
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB;

    $tx_started = $wpdb->query('START TRANSACTION');
    if ($tx_started === false) {
      error_log('WWW_VT: failed to start transaction in consume_if_needed');
      return [false,'db_error'];
    }

    $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d FOR UPDATE",$license_id), ARRAY_A);
    if (!$lic) {
      $wpdb->query('ROLLBACK');
      return [false,'not_found'];
    }

    if (time() > strtotime($lic['expires_at'])) {
      $wpdb->query('ROLLBACK');
      return [false,'expired'];
    }

    if ((int)$lic['remaining_views'] <= 0) {
      $wpdb->query('ROLLBACK');
      return [false,'no_views'];
    }

    if ((int)$lic['remaining_seconds'] <= 0) {
      $wpdb->query('ROLLBACK');
      return [false,'no_seconds'];
    }

    $sessions = json_decode($lic['consumed_sessions'] ?: '[]', true) ?: [];
    if (!in_array($session_id, $sessions, true)) {
      $sessions[] = $session_id;
      if (count($sessions) > 50) {
        $sessions = array_slice($sessions, -50);
      }
    }

    $updated = $wpdb->update($t,[
      'consumed_sessions'=>wp_json_encode($sessions),
      'updated_at'=>gmdate('Y-m-d H:i:s')
    ],['id'=>$license_id]);

    if ($updated === false) {
      $wpdb->query('ROLLBACK');
      error_log('WWW_VT: failed to update license consumption for ID '.$license_id);
      return [false,'db_error'];
    }

    $wpdb->query('COMMIT');
    return [true,(int)$lic['remaining_seconds']];
  }
}
