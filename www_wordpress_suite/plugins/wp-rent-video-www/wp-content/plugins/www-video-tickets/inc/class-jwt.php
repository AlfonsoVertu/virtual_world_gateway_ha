<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_JWT {
  private static function b64url($d){ return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }
  private static function sign($msg,$secret){ return self::b64url(hash_hmac('sha256',$msg,$secret,true)); }

  public static function encode(array $claims, string $secret): string {
    $h=self::b64url(json_encode(['alg'=>'HS256','typ'=>'JWT']));
    $p=self::b64url(json_encode($claims));
    $s=self::sign("$h.$p",$secret);
    return "$h.$p.$s";
  }

  public static function decode_verify(string $jwt, string $secret) {
    $parts = explode('.',$jwt);
    if (count($parts)!==3) return [false,null];
    [$h,$p,$s] = $parts;
    $calc = self::sign("$h.$p",$secret);
    if (!hash_equals($calc,$s)) return [false,null];
    $b64 = strtr($p,'-_','+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
      $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64);
    if ($decoded === false) return [false,null];
    $payload = json_decode($decoded, true);
    $now = time();
    if (isset($payload['nbf']) && $now < (int)$payload['nbf']) return [false,null];
    if (isset($payload['exp']) && $now > (int)$payload['exp']) return [false,null];
    return [true,$payload];
  }
}
