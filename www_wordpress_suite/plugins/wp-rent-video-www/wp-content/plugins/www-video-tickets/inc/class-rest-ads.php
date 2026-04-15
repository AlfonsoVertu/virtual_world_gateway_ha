<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_REST_Ads {
  public static function init() {
    add_action('rest_api_init', function () {
      register_rest_route(WWW_VT_REST_NS, '/ads/list', [
        'methods'  => 'GET',
        'callback' => [__CLASS__, 'handle_list'],
        'permission_callback' => '__return_true',
      ]);
      register_rest_route(WWW_VT_REST_NS, '/ads/mark', [
        'methods'  => 'POST',
        'callback' => [__CLASS__, 'handle_mark'],
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

  private static function normalize_playlist(string $path_prefix, string $playlist): array {
    $prefix = www_vt_normalize_path_prefix($path_prefix);
    $playlist = trim($playlist);
    if ($playlist === '') {
      $playlist = 'master.m3u8';
    }
    $playlist = ltrim($playlist, '/');
    $relative = ltrim($prefix . $playlist, '/');
    $cf = www_vt_cf_host();
    $absolute = $cf ? ('https://' . $cf . '/' . $relative) : ''; // fallback empty if non configurato
    return [$relative, $absolute];
  }

  public static function handle_list(WP_REST_Request $request) {
    nocache_headers();
    [$ok, $claims] = self::get_jwt_claims();
    if (!$ok) {
      return new WP_REST_Response(['error' => 'bad_token'], 403);
    }

    $product_id = (int) $request->get_param('product_id');
    if ($product_id <= 0) {
      $product_id = (int) ($claims['product_id'] ?? 0);
    }

    $path_prefix = (string) ($claims['path_prefix'] ?? '');
    $path_prefix = www_vt_normalize_path_prefix($path_prefix);

    $args = [
      'post_type'      => WWW_VT_Ads::CPT,
      'post_status'    => 'publish',
      'posts_per_page' => 10,
      'orderby'        => 'date',
      'order'          => 'DESC',
      'no_found_rows'  => true,
    ];

    if ($path_prefix !== '') {
      $args['meta_query'] = [[
        'key'     => WWW_VT_Ads::META_PATH,
        'value'   => $path_prefix,
        'compare' => 'LIKE',
      ]];
    }

    $ads = get_posts($args);
    if (empty($ads)) {
      return new WP_REST_Response(['ad' => null]);
    }

    $path_prefix = www_vt_normalize_path_prefix((string) $path_prefix);
    if ($path_prefix !== '') {
      $ads = array_filter($ads, function ($post) use ($path_prefix) {
        $ad_path = get_post_meta($post->ID, WWW_VT_Ads::META_PATH, true);
        $ad_path = www_vt_normalize_path_prefix((string) $ad_path);
        return $ad_path !== '' && strpos($ad_path, $path_prefix) === 0;
      });
    }

    $session_id = (string) ($claims['session_id'] ?? '');
    if ($session_id !== '') {
      global $wpdb;
      $table = $wpdb->prefix . 'www_vt_ads_views';
      $seen_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ad_id FROM $table WHERE session_id = %s",
        $session_id
      ));
      if (!empty($seen_ids)) {
        $seen_ids = array_map('intval', $seen_ids);
        $ads = array_filter($ads, function ($post) use ($seen_ids) {
          return !in_array((int) $post->ID, $seen_ids, true);
        });
      }
    }

    $exclude_param = trim((string) $request->get_param('exclude'));
    if ($exclude_param !== '') {
      $exclude_ids = array_filter(array_map('intval', explode(',', $exclude_param)));
      if (!empty($exclude_ids)) {
        $ads = array_filter($ads, function ($post) use ($exclude_ids) {
          return !in_array((int) $post->ID, $exclude_ids, true);
        });
      }
    }

    if (empty($ads)) {
      return new WP_REST_Response(['ad' => null]);
    }

    $ads = array_values($ads);
    $selected = $ads[array_rand($ads)];

    $ad_path = get_post_meta($selected->ID, WWW_VT_Ads::META_PATH, true);
    $ad_duration = (int) get_post_meta($selected->ID, WWW_VT_Ads::META_DURATION, true);
    $playlist_meta = (string) get_post_meta($selected->ID, WWW_VT_Ads::META_PLAYLIST, true);
    [$relative, $absolute] = self::normalize_playlist((string) $ad_path, $playlist_meta);

    $data = [
      'id'          => (int) $selected->ID,
      'title'       => get_the_title($selected),
      'duration'    => $ad_duration,
      'path_prefix' => www_vt_normalize_path_prefix((string) $ad_path),
      'playlist'    => $relative,
      'src'         => $absolute,
    ];

    if ($product_id > 0) {
      $data['product_id'] = $product_id;
    }

    return new WP_REST_Response(['ad' => $data]);
  }

  public static function handle_mark(WP_REST_Request $request) {
    global $wpdb;

    nocache_headers();
    [$ok, $claims] = self::get_jwt_claims();
    if (!$ok) {
      return new WP_REST_Response(['error' => 'bad_token'], 403);
    }

    $body_raw = $request->get_body();
    $body = json_decode($body_raw ?: '[]', true);
    if (!is_array($body)) {
      return new WP_REST_Response(['error' => 'invalid_body'], 400);
    }

    $ad_id = (int) ($body['ad_id'] ?? 0);
    if ($ad_id <= 0) {
      return new WP_REST_Response(['error' => 'missing_ad'], 400);
    }

    $ad_post = get_post($ad_id);
    if (!$ad_post || $ad_post->post_type !== WWW_VT_Ads::CPT) {
      return new WP_REST_Response(['error' => 'invalid_ad'], 404);
    }

    $product_id = (int) ($body['product_id'] ?? 0);
    if ($product_id <= 0) {
      $product_id = (int) ($claims['product_id'] ?? 0);
    }

    $license_id = (int) ($claims['license_id'] ?? 0);
    $session_id = (string) ($claims['session_id'] ?? '');
    if ($session_id === '') {
      return new WP_REST_Response(['error' => 'invalid_session'], 403);
    }

    $path_prefix = www_vt_normalize_path_prefix((string) ($claims['path_prefix'] ?? ''));
    if ($path_prefix !== '') {
      $ad_path = www_vt_normalize_path_prefix((string) get_post_meta($ad_post->ID, WWW_VT_Ads::META_PATH, true));
      if ($ad_path === '' || strpos($ad_path, $path_prefix) !== 0) {
        return new WP_REST_Response(['error' => 'invalid_scope'], 403);
      }
    }

    $table = $wpdb->prefix . 'www_vt_ads_views';
    $existing = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $table WHERE ad_id = %d AND session_id = %s",
      $ad_id,
      $session_id
    ));

    if ($existing) {
      return new WP_REST_Response(['status' => 'ok']);
    }

    $wpdb->insert($table, [
      'ad_id'      => $ad_id,
      'product_id' => $product_id,
      'license_id' => $license_id,
      'user_id'    => get_current_user_id() ?: 0,
      'session_id' => $session_id,
      'viewed_at'  => gmdate('Y-m-d H:i:s'),
    ], [
      '%d', '%d', '%d', '%d', '%s', '%s',
    ]);

    return new WP_REST_Response(['status' => 'ok']);
  }
}

WWW_VT_REST_Ads::init();
