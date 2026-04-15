<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Keys_Endpoint {
  public function init() {
    add_action('init', [$this,'rewrite']);
    add_filter('query_vars', function($v){ $v[]='www_vt_key_pid'; return $v; });
    add_action('template_redirect', [$this,'serve']);
    // Evita i canonical redirect di WP per le URL /keys/*.key (previene 301 -> trailing slash)
    add_filter('redirect_canonical', [$this, 'disable_canonical_for_keys'], 10, 2);
    register_activation_hook(WWW_VT_DIR.'www-video-tickets.php', function(){ $this->rewrite(); flush_rewrite_rules(); });
    register_deactivation_hook(WWW_VT_DIR.'www-video-tickets.php', function(){ flush_rewrite_rules(); });
  }

  public function disable_canonical_for_keys($redirect_url, $requested) {
    // Proteggi solo le richieste che corrispondono a /keys/<something>.key oppure /keys/<something>.key/
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/keys/[^/]+\\.key/?$#i', $uri)) {
      return false;
    }
    return $redirect_url;
  }

  public function rewrite() {
    // Accept numeric IDs or slugs (alphanum, dash, underscore)
    add_rewrite_rule('^keys/([^/]+)\\.key$','index.php?www_vt_key_pid=$matches[1]','top');
  }

  public function serve() {
    $pid_raw = get_query_var('www_vt_key_pid');
    if (!$pid_raw) return;

    // CORS for keys endpoint — permit requests only from our site
    $allowed_origin = 'https://libertaeazione.it';
    if (!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
      header('Access-Control-Allow-Origin: ' . $allowed_origin);
      header('Access-Control-Allow-Credentials: true');
      header('Access-Control-Allow-Methods: GET, OPTIONS');
      header('Access-Control-Allow-Headers: Origin,Content-Type,Range,Authorization,X-Requested-With');
      header('Access-Control-Expose-Headers: ETag,Content-Length,Accept-Ranges,Content-Range');
    }

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      // respond OK to preflight
      status_header(200);
      exit;
    }

    // Resolve pid: numeric ID or slug -> post ID
    if (ctype_digit((string)$pid_raw)) {
      $pid = (int) $pid_raw;
    } else {
      // Try product slug first (WooCommerce). Then fallback to any post type.
      $post = get_page_by_path($pid_raw, OBJECT, 'product');
      if (!$post) {
        $post = get_page_by_path($pid_raw, OBJECT);
      }
      if (!$post) {
        return $this->deny(404, 'Not Found');
      }
      $pid = (int) $post->ID;
    }

    // Meta prodotto richiesti
    $hex = get_post_meta($pid,'_www_vt_hls_key_hex',true);
    $pre = www_vt_normalize_path_prefix((string)get_post_meta($pid,'_www_vt_path_prefix',true));
    if (!$hex || strlen($hex)!==32 || !$pre) return $this->deny(500,'Key not configured');

    // Verifica JWT nel cookie CF-Auth
    $o = www_vt_opts();
    if (empty($o['jwt_secret'])) return $this->deny(500,'Config');
    $jwt = (string) ($_COOKIE['CF-Auth'] ?? '');
    [$ok,$cl] = WWW_VT_JWT::decode_verify($jwt, (string)$o['jwt_secret']);
    if (!$ok) return $this->deny(403,'Bad token');

    $lic_id=(int)($cl['license_id']??0);
    $scope =(string)($cl['path_prefix']??'');
    if (!$lic_id || !$scope) return $this->deny(403,'Claims');

    // Licenza valida?
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB;
    $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",$lic_id), ARRAY_A);
    if (!$lic) return $this->deny(403,'No license');
    if (time()>strtotime($lic['expires_at'])) return $this->deny(403,'Expired');
    if ((int)$lic['remaining_views']<=0) return $this->deny(403,'No views');

    // Scope coerente: il path del token deve coprire il path del prodotto
    if (strpos(trailingslashit($scope), trailingslashit($pre))!==0) return $this->deny(403,'Scope');

    // Continua con la logica originale per servire la chiave (es. decrypt, echo, ecc.)
    // ... (il codice seguente rimane invariato e prosegue come in origine)

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key = 'www_vt_key_rate_' . md5($lic_id . '|' . $ip);
    $limit = (int) apply_filters('www_vt_keys_endpoint_rate_limit', 60);
    $ttl = (int) apply_filters('www_vt_keys_endpoint_rate_ttl', 60);
    $limit = $limit > 0 ? $limit : 60;
    $ttl = $ttl > 0 ? $ttl : 60;
    $count = (int) get_transient($rate_key);
    if ($count >= $limit) {
      error_log("WWW_VT: rate limit exceeded for license {$lic_id} from {$ip}");
      return $this->deny(429,'Rate limit');
    }
    set_transient($rate_key, $count + 1, $ttl);

    nocache_headers();
    header('Content-Type: application/octet-stream');
    header('Content-Length: 16');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    echo pack('H*', $hex);
    exit;
  }

  private function deny($code,$msg){
    status_header($code);
    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden: '.$msg;
    exit;
  }
}
