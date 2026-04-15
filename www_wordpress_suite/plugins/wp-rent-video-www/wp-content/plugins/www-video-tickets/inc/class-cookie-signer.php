<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Cookie_Signer {
  public static function private_key(): string {
    if (defined('WWW_VT_PRIVATE_KEY')) return WWW_VT_PRIVATE_KEY;
    $opts = www_vt_opts();
    return (string)($opts['private_key_pem'] ?? '');
  }

  /**
   * URL-safe base64 come richiesto da CloudFront: + -> -, / -> _, = -> ~
   */
  private static function b64url(string $data): string {
    $b64 = base64_encode($data);
    return strtr($b64, ['+'=>'-','/'=>'_','='=>'~']);
  }

  /**
   * Firma la policy JSON e restituisce le cookie firmate.
   * Ritorna array vuoto se la firma non riesce, loggando l'errore per debug.
   */
  public static function sign_policy_cookies(string $policy_json, string $key_pair_id): array {
    $pk_pem = self::private_key();
    if (empty($pk_pem)) {
      error_log('WWW_VT: CloudFront private key missing for CookieSigner');
      return [];
    }

    $pkey = openssl_pkey_get_private($pk_pem);
    if ($pkey === false) {
      $err = '';
      while ($e = openssl_error_string()) { $err .= $e . ' | '; }
      error_log('WWW_VT: openssl_pkey_get_private failed: ' . $err);
      return [];
    }

    $sig = '';
    $ok = openssl_sign($policy_json, $sig, $pkey, OPENSSL_ALGO_SHA1);
    if (function_exists('openssl_pkey_free')) {
      openssl_pkey_free($pkey);
    } else {
      openssl_free_key($pkey);
    }

    if (!$ok) {
      $err = '';
      while ($e = openssl_error_string()) { $err .= $e . ' | '; }
      error_log('WWW_VT: openssl_sign failed: ' . $err);
      return [];
    }

    return [
      'CloudFront-Policy'      => self::b64url($policy_json),
      'CloudFront-Signature'   => self::b64url($sig),
      'CloudFront-Key-Pair-Id' => $key_pair_id,
    ];
  }
}
