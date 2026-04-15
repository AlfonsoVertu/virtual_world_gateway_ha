<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Product_Meta {
  public function init() {
    add_action('add_meta_boxes', [$this,'box']);
    add_action('save_post_product', [$this,'save']);
  }

  public function box() {
    add_meta_box('www_vt_meta','WWW Video Tickets',[$this,'render'],'product','side');
  }

  public function render($post) {
    $max = get_post_meta($post->ID,'_www_vt_max_views',true);
    $win = get_post_meta($post->ID,'_www_vt_window_hours',true);
    $pre = get_post_meta($post->ID,'_www_vt_path_prefix',true);
    $minutes = get_post_meta($post->ID,'_www_vt_total_minutes',true);
    $enable_ads = get_post_meta($post->ID,'_www_vt_enable_ads',true);
    $ads_interval = get_post_meta($post->ID,'_www_vt_ads_interval_override',true);
    $ads_duration = get_post_meta($post->ID,'_www_vt_ads_duration_override',true);
    $video_duration = get_post_meta($post->ID,'_www_vt_video_duration_seconds',true);
    $hex = get_post_meta($post->ID,'_www_vt_hls_key_hex',true);
    $kuri= get_post_meta($post->ID,'_www_vt_hls_key_uri',true);
    wp_nonce_field('www_vt_meta','www_vt_meta_nonce'); ?>
    <p><label>Max visioni<br><input type="number" min="1" name="www_vt_max_views" value="<?php echo esc_attr($max); ?>" style="width:100%"></label></p>
    <p><label>Finestra (ore)<br><input type="number" min="1" name="www_vt_window_hours" value="<?php echo esc_attr($win); ?>" style="width:100%"></label></p>
    <p><label>Minuti acquistati<br><input type="number" min="1" name="www_vt_total_minutes" value="<?php echo esc_attr($minutes); ?>" style="width:100%"></label></p>
    <p><label><input type="checkbox" name="www_vt_enable_ads" value="1" <?php checked($enable_ads,'1'); ?>> Abilita ADS</label></p>
    <p><label>Override intervallo ADS (secondi)<br><input type="number" min="0" name="www_vt_ads_interval_override" value="<?php echo esc_attr($ads_interval); ?>" style="width:100%"></label></p>
    <p><label>Override durata ADS (secondi)<br><input type="number" min="0" name="www_vt_ads_duration_override" value="<?php echo esc_attr($ads_duration); ?>" style="width:100%"></label></p>
    <p><label>Durata video (secondi) per controlli anti-abuso<br><input type="number" min="0" name="www_vt_video_duration_seconds" value="<?php echo esc_attr($video_duration); ?>" style="width:100%"></label></p>
    <p><label>Path prefix HLS (es. /videos/<?php echo (int)$post->ID; ?>/)<br><input type="text" name="www_vt_path_prefix" value="<?php echo esc_attr($pre); ?>" style="width:100%"></label></p>
    <hr><p><strong>Cifratura AES‑128 (HLS)</strong></p>
    <p><label>Key HEX (32 caratteri → 16 byte)<br><input type="text" name="www_vt_hls_key_hex" maxlength="32" value="<?php echo esc_attr($hex); ?>" style="width:100%"></label></p>
    <p><label>Key URI nella playlist (es. /keys/<?php echo (int)$post->ID; ?>.key)<br><input type="text" name="www_vt_hls_key_uri" value="<?php echo esc_attr($kuri); ?>" style="width:100%"></label></p>
    <?php
  }

  public function save($post_id) {
    if (!isset($_POST['www_vt_meta_nonce']) || !wp_verify_nonce($_POST['www_vt_meta_nonce'],'www_vt_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product',$post_id)) return;

    update_post_meta($post_id,'_www_vt_max_views', max(0,(int)($_POST['www_vt_max_views']??0)));
    update_post_meta($post_id,'_www_vt_window_hours', max(0,(int)($_POST['www_vt_window_hours']??0)));
    $path = www_vt_normalize_path_prefix((string)($_POST['www_vt_path_prefix'] ?? ''));
    update_post_meta($post_id,'_www_vt_path_prefix', $path);
    update_post_meta($post_id,'_www_vt_total_minutes', max(0,(int)($_POST['www_vt_total_minutes']??0)));
    update_post_meta($post_id,'_www_vt_enable_ads', isset($_POST['www_vt_enable_ads']) ? '1' : '0');
    update_post_meta($post_id,'_www_vt_ads_interval_override', max(0,(int)($_POST['www_vt_ads_interval_override']??0)));
    update_post_meta($post_id,'_www_vt_ads_duration_override', max(0,(int)($_POST['www_vt_ads_duration_override']??0)));
    update_post_meta($post_id,'_www_vt_video_duration_seconds', max(0,(int)($_POST['www_vt_video_duration_seconds']??0)));

    $hex = strtolower(preg_replace('/[^0-9a-f]/','',(string)($_POST['www_vt_hls_key_hex']??'')));
    if ($hex && strlen($hex)!==32) add_settings_error('www_vt','keyhex','Key HEX deve avere 32 caratteri.');
    update_post_meta($post_id,'_www_vt_hls_key_hex',$hex);
    update_post_meta($post_id,'_www_vt_hls_key_uri', sanitize_text_field($_POST['www_vt_hls_key_uri']??''));
  }
}
