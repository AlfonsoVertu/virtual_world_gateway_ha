<?php
if (!defined('ABSPATH')) exit;

function www_vt_opts(): array {
  $defaults = [
    'cloudfront_domain'  => '',
    'key_pair_id'        => '',
    'private_key_pem'    => '',
    'cookie_ttl_minutes' => 120,
    'jwt_secret'         => '',
  ];
  $opts = get_option(WWW_VT_OPT, []);
  return wp_parse_args($opts, $defaults);
}

function www_vt_cf_host(): string {
  $d = trim(www_vt_opts()['cloudfront_domain']);
  if (!$d) return '';
  if (stripos($d,'http')===0) {
    $h = parse_url($d, PHP_URL_HOST);
    return $h ?: $d;
  }
  return $d;
}

function www_vt_normalize_path_prefix(string $path): string {
  $path = trim($path);
  if ($path === '') return '';
  $path = '/' . ltrim($path, '/');
  return trailingslashit($path);
}

function www_vt_cookie_domain(string $cf_host): string {
  $cf_host = strtolower($cf_host);
  $site = parse_url(home_url(), PHP_URL_HOST);
  $site = $site ? strtolower($site) : $site;
  if (!$site) return $cf_host;

  if ($cf_host === $site) {
    return $cf_host;
  }

  $cf_parts = array_reverse(explode('.', $cf_host));
  $site_parts = array_reverse(explode('.', $site));
  $common = [];
  $limit = min(count($cf_parts), count($site_parts));

  for ($i = 0; $i < $limit; $i++) {
    if ($cf_parts[$i] !== $site_parts[$i]) break;
    $common[] = $cf_parts[$i];
  }

  if (count($common) < 2) {
    return $cf_host;
  }

  $base = implode('.', array_reverse($common));
  return '.' . strtolower($base);
}

function www_vt_page_has_shortcode($tag): bool {
  global $post;
  if (!$post || !is_singular()) return false;
  if (has_shortcode($post->post_content, $tag)) return true;
  return stripos($post->post_content, '['.$tag)!==false;
}

function www_vt_user_license($user_id, $product_id) {
  $lic = WWW_VT_License_Store::get_active_for_user_product($user_id, $product_id);
  if (!$lic) return [false, null];
  if (time()>strtotime($lic['expires_at'])) return [false, null];
  if ((int)$lic['remaining_views']<=0) return [false, null];
  if ((int)($lic['remaining_seconds'] ?? 0) <= 0) return [false, null];
  $lic['path_prefix'] = www_vt_normalize_path_prefix((string)$lic['path_prefix']);
  return [true, $lic];
}
