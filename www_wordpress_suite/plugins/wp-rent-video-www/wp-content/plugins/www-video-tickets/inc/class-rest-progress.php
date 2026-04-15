<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_REST_Progress {
  private static function rest_response($data, $status = 200) {
    $response = new WP_REST_Response($data, $status);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->header('Pragma', 'no-cache');
    $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
    $response->header('Vary', 'Cookie');
    return $response;
  }

  private static function maybe_rate_limit() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    if ($ip === '') {
      return null;
    }
    $window = 0.5;
    if (function_exists('apply_filters')) {
      $window = (float) apply_filters('www_vt_progress_rate_limit_window', $window, $ip);
    }
    if ($window < 0.1) {
      $window = 0.1;
    }
    $cache_key = 'www_vt_progress_rl_' . md5($ip);
    $now = microtime(true);
    $last = false;
    if (function_exists('wp_cache_get')) {
      $last = wp_cache_get($cache_key, 'www_vt');
    }
    if ($last === false) {
      $last = get_transient($cache_key);
    }
    if ($last !== false) {
      $last = (float) $last;
      if (($now - $last) < $window) {
        if (function_exists('do_action')) {
          do_action('www_vt_progress_rate_limited', $ip, $window);
        }
        return self::rest_response(['error' => 'rate_limited'], 429);
      }
    }
    $expire = max(1, (int) ceil($window * 2));
    if (function_exists('wp_cache_set')) {
      wp_cache_set($cache_key, (string) $now, 'www_vt', $expire);
    }
    set_transient($cache_key, (string) $now, $expire);
    return null;
  }

  public static function init() {
    add_action('rest_api_init', function () {
      register_rest_route(WWW_VT_REST_NS, '/view/progress', [
        'methods'  => 'POST',
        'callback' => [__CLASS__, 'handle_progress'],
        'permission_callback' => '__return_true',
      ]);
      register_rest_route(WWW_VT_REST_NS, '/view/position', [
        'methods'  => 'GET',
        'callback' => [__CLASS__, 'handle_position'],
        'permission_callback' => '__return_true',
      ]);
    });
  }

  private static function get_jwt_claims() {
    $opts = www_vt_opts();
    if (empty($opts['jwt_secret'])) {
      return [false, null];
    }
    $jwt = (string) ($_COOKIE['CF-Auth'] ?? '');
    if ($jwt === '') {
      return [false, null];
    }
    return WWW_VT_JWT::decode_verify($jwt, (string) $opts['jwt_secret']);
  }

  public static function handle_progress(WP_REST_Request $request) {
    global $wpdb;

    nocache_headers();
    [$ok, $claims] = self::get_jwt_claims();
    if (!$ok) {
      return self::rest_response(['error' => 'Bad token'], 403);
    }

    $rate_response = self::maybe_rate_limit();
    if ($rate_response instanceof WP_REST_Response) {
      return $rate_response;
    }

    $license_id = (int) ($claims['license_id'] ?? 0);
    $session_id = (string) ($claims['session_id'] ?? '');
    if (!$license_id || $session_id === '') {
      return self::rest_response(['error' => 'Claims'], 403);
    }

    $body_raw = $request->get_body();
    $body = json_decode($body_raw ?: '[]', true);
    if (!is_array($body)) {
      return self::rest_response(['error' => 'invalid_body'], 400);
    }

    $watched_seconds = max(0, (int) ($body['watched_seconds'] ?? 0));
    if (isset($body['position_seconds'])) {
      $position_seconds = max(0, (int) $body['position_seconds']);
    } else {
      $minutes = max(0, (int) ($body['position_minutes'] ?? 0));
      $seconds = max(0, (int) ($body['position_seconds_partial'] ?? 0));
      $position_seconds = $minutes * 60 + $seconds;
    }
    $product_id = (int) ($body['product_id'] ?? 0);
    $video_duration = max(0, (int) ($body['video_duration_seconds'] ?? 0));

    if ($video_duration > 0) {
      if ($watched_seconds > $video_duration) {
        $watched_seconds = $video_duration;
      }
      if ($position_seconds > $video_duration) {
        $position_seconds = $video_duration;
      }
    }

    if ($product_id <= 0) {
      $product_id = (int) ($claims['product_id'] ?? 0);
    }

    if ($product_id <= 0) {
      $path_prefix = (string) ($claims['path_prefix'] ?? '');
      if ($path_prefix !== '') {
        $posts = get_posts([
          'post_type'   => 'product',
          'meta_key'    => '_www_vt_path_prefix',
          'meta_value'  => $path_prefix,
          'numberposts' => 1,
        ]);
        if (!empty($posts)) {
          $product_id = (int) $posts[0]->ID;
        }
      }
    }

    $t_license = $wpdb->prefix . WWW_VT_DB;
    $t_progress = $wpdb->prefix . 'www_vt_progress';

    $license = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_license WHERE id = %d", $license_id), ARRAY_A);
    if (!$license) {
      return self::rest_response(['error' => 'No license'], 403);
    }

    if (time() > strtotime($license['expires_at'])) {
      return self::rest_response([
        'status' => 'expired',
        'remaining_seconds' => 0,
      ]);
    }

    $threshold = max(1, (int) get_option('www_vt_consumption_threshold_seconds', 30));
    $heartbeat_ms = max(1000, (int) get_option('www_vt_heartbeat_interval_ms', 10000));
    $rate_max = max(1, (int) ceil($heartbeat_ms / 1000) * 2);
    $max_delta = max(1, min($threshold, $rate_max));
    if (function_exists('apply_filters')) {
      /**
       * Filters the maximum number of seconds that can be consumed in a single update.
       *
       * @param int $max_delta   Calculated maximum delta in seconds.
       * @param int $threshold   Configured threshold option value.
       * @param int $license_id  License identifier from the JWT.
       * @param string $session_id Session identifier from the JWT.
       * @param int $rate_max    Maximum delta derived from the heartbeat cadence.
       */
      $max_delta = (int) apply_filters('www_vt_progress_max_delta', $max_delta, $threshold, $license_id, $session_id, $rate_max);
      if ($max_delta < 1) {
        $max_delta = 1;
      }
    }
    if ($max_delta > $rate_max) {
      $max_delta = $rate_max;
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_progress WHERE license_id = %d AND session_id = %s", $license_id, $session_id), ARRAY_A);

    if (!$row) {
      $wpdb->insert($t_progress, [
        'license_id'       => $license_id,
        'product_id'       => $product_id,
        'user_id'          => get_current_user_id() ?: 0,
        'session_id'       => $session_id,
        'watched_seconds'  => $watched_seconds,
        'position_seconds' => $position_seconds,
        'complete'         => 0,
      ]);
      $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_progress WHERE id = %d", (int) $wpdb->insert_id), ARRAY_A);
    } else {
      $old_watched = (int) $row['watched_seconds'];
      $old_position = (int) $row['position_seconds'];
      $new_max = max($old_watched, $watched_seconds);
      if ($video_duration > 0 && $new_max > $video_duration) {
        $new_max = $video_duration;
      }
      $delta = $new_max - $old_watched;

      if ($delta > 0) {
        if ($video_duration > 0 && $delta > $video_duration) {
          return self::rest_response(['error' => 'invalid_progress'], 400);
        }
        if ($delta > $max_delta) {
          return self::rest_response(['error' => 'invalid_progress'], 400);
        }

        $updated = $wpdb->query($wpdb->prepare(
          "UPDATE $t_license SET remaining_seconds = GREATEST(remaining_seconds - %d, 0) WHERE id = %d AND remaining_seconds > 0",
          $delta,
          $license_id
        ));

        if ($updated) {
          $wpdb->update($t_progress, ['watched_seconds' => $new_max], ['id' => $row['id']]);
          $row['watched_seconds'] = $new_max;
          $new_remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT remaining_seconds FROM $t_license WHERE id = %d", $license_id));
          if ($new_remaining === 0) {
            $wpdb->update($t_progress, ['complete' => 1], ['id' => $row['id']]);
            return self::rest_response([
              'status'            => 'consumed',
              'remaining_seconds' => 0,
              'position_seconds'  => $position_seconds,
            ]);
          }
        } else {
          $wpdb->update($t_progress, ['watched_seconds' => $new_max], ['id' => $row['id']]);
          $row['watched_seconds'] = $new_max;
          $new_remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT remaining_seconds FROM $t_license WHERE id = %d", $license_id));
          return self::rest_response([
            'status'            => 'no_seconds_left',
            'remaining_seconds' => $new_remaining,
          ]);
        }
      }

      if ($position_seconds + 2 < $old_position && $delta > 0) {
        return self::rest_response(['error' => 'invalid_position'], 400);
      }

      if ($delta > 0) {
        $position_delta = $position_seconds - $old_position;
        $tolerance = max(5, $max_delta);
        if ($position_delta > ($delta + $tolerance)) {
          return self::rest_response(['error' => 'invalid_position'], 400);
        }
      }

      if ($position_seconds > $old_position) {
        $wpdb->update($t_progress, ['position_seconds' => $position_seconds], ['id' => $row['id']]);
        $row['position_seconds'] = $position_seconds;
      }
    }

    $new_remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT remaining_seconds FROM $t_license WHERE id = %d", $license_id));

    return self::rest_response([
      'status'            => 'ok',
      'watched_seconds'   => (int) $row['watched_seconds'],
      'position_seconds'  => (int) $row['position_seconds'],
      'remaining_seconds' => $new_remaining,
    ]);
  }

  public static function handle_position(WP_REST_Request $request) {
    global $wpdb;

    nocache_headers();
    [$ok, $claims] = self::get_jwt_claims();
    if (!$ok) {
      return self::rest_response(['error' => 'Bad token'], 403);
    }

    $license_id = (int) ($claims['license_id'] ?? 0);
    $session_id = (string) ($claims['session_id'] ?? '');
    if (!$license_id || $session_id === '') {
      return self::rest_response(['error' => 'Claims'], 403);
    }

    $product_id = (int) $request->get_param('product_id');
    $t_progress = $wpdb->prefix . 'www_vt_progress';

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT position_seconds FROM $t_progress WHERE license_id = %d AND session_id = %s AND product_id = %d",
      $license_id,
      $session_id,
      $product_id
    ), ARRAY_A);

    return self::rest_response([
      'position_seconds' => ($row ? (int) $row['position_seconds'] : 0),
    ]);
  }
}

WWW_VT_REST_Progress::init();
