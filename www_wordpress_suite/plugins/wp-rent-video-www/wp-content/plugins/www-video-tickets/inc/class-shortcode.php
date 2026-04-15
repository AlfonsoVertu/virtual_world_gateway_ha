<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Shortcode {
  public function init() {
    add_shortcode('video_watch',[$this,'render']);
    add_shortcode('www_vt_ads',[$this,'render_ads']);
    add_action('template_redirect',[$this,'maybe_set_cookies']);
    add_action('wp_enqueue_scripts',[$this,'assets']);
  }

  public function assets() {
    wp_register_script('www-vt-player', WWW_VT_URL.'assets/player.js', [], WWW_VT_VER, true);
  }

  public function render($atts) {
    $a = shortcode_atts([
      'product_id'=>0,
      'playlist'=>'',
      'width'=>'100%',
      'maxwidth'=>'960px'
    ], $atts);
    $pid = (int)$a['product_id'];
    if (!$pid) return 'Configura product_id.';
    if (!is_user_logged_in()) return 'Accedi per vedere il video.';

    [$ok,$lic] = www_vt_user_license(get_current_user_id(), $pid);
    if (!$ok) return 'Licenza non attiva o minuti esauriti.';

    $cf = www_vt_cf_host();
    if (!$cf) return 'CloudFront non configurato.';
    $path_prefix = www_vt_normalize_path_prefix((string)$lic['path_prefix']);
    $playlist = $a['playlist'] ?: (trailingslashit($path_prefix).'master.m3u8');
    $src = ltrim($playlist, '/');

    $ads_enabled = get_post_meta($pid, '_www_vt_enable_ads', true) === '1';
    $ads_interval = (int) get_option('www_vt_ads_interval_seconds', 0);
    $ads_duration = (int) get_option('www_vt_ads_duration_seconds', 0);
    if ($ads_enabled) {
      $override_interval = (int) get_post_meta($pid, '_www_vt_ads_interval_override', true);
      $override_duration = (int) get_post_meta($pid, '_www_vt_ads_duration_override', true);
      if ($override_interval > 0) {
        $ads_interval = $override_interval;
      }
      if ($override_duration > 0) {
        $ads_duration = $override_duration;
      }
    }
    $video_duration_seconds = (int) get_post_meta($pid, '_www_vt_video_duration_seconds', true);

    wp_enqueue_script('www-vt-player');
    $token = $this->session_token($lic);
    wp_localize_script('www-vt-player', 'WWW_VT', [
      'src'   => 'https://'.$cf.'/'.$src,
      'rest'  => esc_url_raw( rest_url(WWW_VT_REST_NS) ),
      'token' => $token,
      'product_id' => (int)$pid,
      'threshold_seconds' => (int)get_option('www_vt_consumption_threshold_seconds',30),
      'heartbeat_ms' => (int)get_option('www_vt_heartbeat_interval_ms',10000),
      'position_save_ms' => (int)get_option('www_vt_position_save_interval_ms',60000),
      'ads_enabled' => $ads_enabled ? 1 : 0,
      'ads_interval_seconds' => $ads_interval,
      'ads_duration_seconds' => $ads_duration,
      'video_duration_seconds' => $video_duration_seconds,
    ]);

    ob_start(); ?>
    <video id="www-vt-video" controls playsinline preload="none" style="width:<?php echo esc_attr($a['width']); ?>;max-width:<?php echo esc_attr($a['maxwidth']); ?>"></video>
    <?php return ob_get_clean();
  }

  public function render_ads($atts) {
    $a = shortcode_atts([
      'id' => 0,
      'width' => '100%',
      'maxwidth' => '640px',
    ], $atts);

    $ad_id = (int) $a['id'];
    if ($ad_id <= 0) {
      return 'Specifica l\'ID della ADS.';
    }

    $post = get_post($ad_id);
    if (!$post || $post->post_type !== WWW_VT_Ads::CPT) {
      return 'ADS non trovata.';
    }

    $cf = www_vt_cf_host();
    if (!$cf) {
      return 'CloudFront non configurato.';
    }

    $path = get_post_meta($post->ID, WWW_VT_Ads::META_PATH, true);
    $path = www_vt_normalize_path_prefix((string) $path);
    if ($path === '') {
      return 'Path ADS mancante.';
    }

    $playlist = (string) get_post_meta($post->ID, WWW_VT_Ads::META_PLAYLIST, true);
    $playlist = trim($playlist) !== '' ? ltrim($playlist, '/') : 'master.m3u8';
    $relative = ltrim($path . $playlist, '/');
    $src = 'https://' . $cf . '/' . $relative;
    $duration = (int) get_post_meta($post->ID, WWW_VT_Ads::META_DURATION, true);

    $video_id = 'www-vt-ad-preview-' . $post->ID;

    if (!wp_script_is('hls.js', 'registered')) {
      wp_register_script('hls.js', 'https://cdn.jsdelivr.net/npm/hls.js@latest', [], null, true);
    }
    $inline = "(function(){var v=document.getElementById('" . esc_js($video_id) . "');if(!v)return;if(v.canPlayType('application/vnd.apple.mpegurl'))return;if(!window.Hls||!Hls.isSupported())return;var hls=new Hls({xhrSetup:function(x){x.withCredentials=true;}});hls.loadSource('" . esc_js($src) . "');hls.attachMedia(v);})();";
    wp_add_inline_script('hls.js', $inline);
    wp_enqueue_script('hls.js');

    ob_start();
    ?>
    <div class="www-vt-ad-preview" style="max-width:<?php echo esc_attr($a['maxwidth']); ?>">
      <h3><?php echo esc_html(get_the_title($post)); ?></h3>
      <?php if ($duration > 0): ?>
        <p>Durata: <?php echo esc_html($duration); ?>s</p>
      <?php endif; ?>
      <video id="<?php echo esc_attr($video_id); ?>" controls playsinline preload="none" style="width:<?php echo esc_attr($a['width']); ?>">
        <source src="<?php echo esc_url($src); ?>" type="application/vnd.apple.mpegurl" />
      </video>
      <?php if (!empty($post->post_content)): ?>
        <div class="www-vt-ad-content"><?php echo wp_kses_post(wpautop($post->post_content)); ?></div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
  }

  private function session_token(array $lic): string {
    $o = www_vt_opts();
    $secret = (string)($o['jwt_secret'] ?? '');
    if (trim($secret) === '') {
      error_log('WWW_VT: JWT secret missing, cannot create session token');
      return '';
    }
    $ttl_minutes = (int)($o['cookie_ttl_minutes'] ?? 120);
    if ($ttl_minutes <= 0) {
      $ttl_minutes = 120;
    }
    $ttl = max(60, $ttl_minutes * 60);
    $now = time(); $exp = $now + $ttl;
    $path = www_vt_normalize_path_prefix((string)$lic['path_prefix']);
    $existing_session_id = '';
    if (!empty($_COOKIE['CF-Auth'])) {
      [$jwt_ok, $jwt_claims] = WWW_VT_JWT::decode_verify((string) $_COOKIE['CF-Auth'], $secret);
      if ($jwt_ok && (int)($jwt_claims['license_id'] ?? 0) === (int)$lic['id']) {
        $existing_session_id = (string) ($jwt_claims['session_id'] ?? '');
      }
    }
    $session_id = $existing_session_id !== '' ? $existing_session_id : wp_generate_uuid4();
    $claims = [
      'license_id' => (int)$lic['id'],
      'session_id' => $session_id,
      'path_prefix'=> $path,
      'product_id' => (int)$lic['product_id'],
      'nbf' => $now - 30,
      'exp' => $exp
    ];
    return WWW_VT_JWT::encode($claims, $secret);
  }

  public function maybe_set_cookies() {
    if (!is_user_logged_in() || !is_singular() || !www_vt_page_has_shortcode('video_watch')) return;

    global $post;
    if (!preg_match('/\\[video_watch[^\\]]*product_id="(\d+)"/', $post->post_content, $m)) return;
    $pid = (int)$m[1];

    [$ok,$lic] = www_vt_user_license(get_current_user_id(), $pid);
    if (!$ok) return;

    $o = www_vt_opts(); $cf = www_vt_cf_host();
    if (!$cf || empty($o['key_pair_id'])) {
      error_log('WWW_VT: CloudFront domain or Key Pair ID missing, cannot set cookies');
      return;
    }

    $secret = (string)($o['jwt_secret'] ?? '');
    if (trim($secret) === '') {
      error_log('WWW_VT: JWT secret missing, cannot set cookies');
      return;
    }

    $ttl_minutes = (int)($o['cookie_ttl_minutes'] ?? 120);
    if ($ttl_minutes <= 0) {
      $ttl_minutes = 120;
    }
    $ttl_seconds = max(60, $ttl_minutes * 60);
    $exp = time() + $ttl_seconds;

    $path_prefix = www_vt_normalize_path_prefix((string)$lic['path_prefix']);
    $path = trailingslashit($path_prefix);
    $policy = wp_json_encode([
      'Statement'=>[[
        'Resource'=>'https://'.$cf . $path . '*',
        'Condition'=>['DateLessThan'=>['AWS:EpochTime'=>$exp]]
      ]]
    ], JSON_UNESCAPED_SLASHES);

    $cookies = WWW_VT_Cookie_Signer::sign_policy_cookies($policy, (string)$o['key_pair_id']);
    if (empty($cookies)) {
      error_log('WWW_VT: CloudFront cookie signing failed. No cookies will be set.');
      return;
    }

    $session_id = wp_generate_uuid4();
    if (!empty($_COOKIE['CF-Auth'])) {
      [$cookie_ok, $cookie_claims] = WWW_VT_JWT::decode_verify((string) $_COOKIE['CF-Auth'], $secret);
      if ($cookie_ok && (int)($cookie_claims['license_id'] ?? 0) === (int)$lic['id']) {
        $session_id = (string) ($cookie_claims['session_id'] ?? $session_id);
      }
    }

    $jwt = WWW_VT_JWT::encode([
      'license_id' => (int)$lic['id'],
      'session_id' => $session_id,
      'path_prefix'=> $path_prefix,
      'product_id' => (int)$lic['product_id'],
      'nbf' => time() - 30,
      'exp' => $exp
    ], $secret);

    $cookies_to_set = $cookies;
    if ($jwt !== '') {
      $cookies_to_set['CF-Auth'] = $jwt;
    }

    $this->www_vt_set_signed_cookies($cookies_to_set, $ttl_minutes);
  }

  /**
   * Normalize a CloudFront signature so it uses standard base64 alphabet and padding.
   */
  private function www_vt_normalize_signature(string $s): string {
    $s = rawurldecode($s);
    $s = str_replace('~', '=', $s);
    $s = str_replace(['-', '_'], ['+', '/'], $s);
    $mod = strlen($s) % 4;
    if ($mod > 0) {
      $s .= str_repeat('=', 4 - $mod);
    }

    return trim($s);
  }

  /**
   * Set CloudFront + session cookies so they are sent by the browser on cross-site requests.
   * - cookie domain derived from helper/constant when available, fallback to .libertaeazione.it
   * - SameSite=None + Secure required for cross-site cookies
   * - CF-Auth kept HttpOnly unless a filter allows JS access
   *
   * $cookies : associative array name => value
   */
  private function www_vt_set_signed_cookies(array $cookies, int $ttl_minutes = 120) {
    if (defined('WWW_VT_COOKIE_DOMAIN')) {
      $cookie_domain = WWW_VT_COOKIE_DOMAIN;
    } elseif (function_exists('www_vt_cookie_domain')) {
      $cookie_domain = www_vt_cookie_domain(www_vt_cf_host());
    } else {
      $cookie_domain = '.libertaeazione.it';
    }
    $ttl_minutes = $ttl_minutes > 0 ? $ttl_minutes : 120;
    $expires = time() + ($ttl_minutes * 60);
    foreach ($cookies as $name => $value) {
      if (strtolower($name) === 'cloudfront-signature') {
        $value = $this->www_vt_normalize_signature((string) $value);
      }
      $httponly = true;
      if (strtolower($name) === 'cf-auth' || strtolower($name) === 'cf_auth') {
        /**
         * Filters whether the CF-Auth cookie should be accessible to JavaScript.
         * Returning false makes the cookie available to JS (HttpOnly = false).
         *
         * @since 1.0.0
         *
         * @param bool   $httponly Default true to keep the cookie HttpOnly.
         * @param string $name     Cookie name.
         * @param string $value    Cookie value.
         */
        $httponly = (bool) apply_filters('www_vt_cf_auth_cookie_httponly', true, $name, $value);
      }

      // PHP >= 7.3: use options array
      if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, [
          'expires'  => $expires,
          'path'     => '/',
          'domain'   => $cookie_domain,
          'secure'   => true,
          'httponly' => $httponly,
          'samesite' => 'None',
        ]);
      } else {
        // fallback header for older PHP versions
        $cookie_hdr = sprintf(
          '%s=%s; Expires=%s; Path=/; Domain=%s; Secure; %s; SameSite=None',
          $name,
          $value,
          gmdate('D, d M Y H:i:s T', $expires),
          $cookie_domain,
          $httponly ? 'HttpOnly' : ''
        );
        header('Set-Cookie: ' . $cookie_hdr, false);
      }
    }
  }
}
