<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_REST {
  public function init() {
    add_action('rest_api_init', function(){
      register_rest_route(WWW_VT_REST_NS,'/view/start',[
        'methods'=>'POST',
        'permission_callback'=>'__return_true',
        'callback'=>[$this,'start_view']
      ]);
    });
  }

  public function start_view(WP_REST_Request $r) {
    $token = (string) $r->get_param('token');
    $secret = (string) www_vt_opts()['jwt_secret'];
    if (!$token || !$secret) return new WP_Error('bad_request','token missing',['status'=>400]);

    [$ok,$cl] = WWW_VT_JWT::decode_verify($token,$secret);
    if (!$ok) return new WP_Error('forbidden','invalid token',['status'=>403]);

    $lic_id = (int)($cl['license_id'] ?? 0);
    $sid    = (string)($cl['session_id'] ?? '');
    if (!$lic_id || !$sid) return new WP_Error('bad_request','claims missing',['status'=>400]);

    [$ok2,$rem] = WWW_VT_License_Store::consume_if_needed($lic_id,$sid);
    if (!$ok2) {
      $status = 403;
      if ($rem === 'no_views') {
        $status = 402;
      } elseif ($rem === 'no_seconds') {
        $status = 402;
      }
      return new WP_Error('not_allowed',$rem,['status'=>$status]);
    }
    return ['ok'=>true,'remaining_seconds'=>$rem];
  }
}
