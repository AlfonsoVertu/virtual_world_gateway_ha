<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Ads {
  const CPT = 'www_vt_ad';
  const META_PATH = '_www_vt_ad_path';
  const META_DURATION = '_www_vt_ad_duration';
  const META_PLAYLIST = '_www_vt_ad_playlist';

  public function init() {
    add_action('init', [$this, 'register_cpt']);
    add_action('add_meta_boxes', [$this, 'add_meta_box']);
    add_action('save_post_' . self::CPT, [$this, 'save_meta']);
  }

  public function register_cpt() {
    $labels = [
      'name'               => 'ADS Video',
      'singular_name'      => 'ADS Video',
      'menu_name'          => 'ADS Video',
      'name_admin_bar'     => 'ADS Video',
      'add_new'            => 'Aggiungi nuova',
      'add_new_item'       => 'Aggiungi nuova ADS',
      'new_item'           => 'Nuova ADS',
      'edit_item'          => 'Modifica ADS',
      'view_item'          => 'Visualizza ADS',
      'all_items'          => 'Tutte le ADS',
      'search_items'       => 'Cerca ADS',
      'parent_item_colon'  => 'ADS parent:',
      'not_found'          => 'Nessuna ADS trovata.',
      'not_found_in_trash' => 'Nessuna ADS nel cestino.',
    ];

    register_post_type(self::CPT, [
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'menu_position' => 25,
      'show_in_rest' => false,
      'supports' => ['title', 'editor'],
      'has_archive' => false,
      'capability_type' => 'post',
    ]);
  }

  public function add_meta_box() {
    add_meta_box('www_vt_ads_meta', 'Dettagli ADS', [$this, 'render_meta_box'], self::CPT, 'normal');
  }

  public function render_meta_box($post) {
    $path = get_post_meta($post->ID, self::META_PATH, true);
    $duration = get_post_meta($post->ID, self::META_DURATION, true);
    $playlist = get_post_meta($post->ID, self::META_PLAYLIST, true);
    wp_nonce_field('www_vt_ads_meta', 'www_vt_ads_meta_nonce');
    ?>
    <p><label>Path prefix CloudFront/S3 (es. /videos/<?php echo (int) $post->ID; ?>/ad1/)<br>
      <input type="text" name="www_vt_ad_path" value="<?php echo esc_attr($path); ?>" style="width:100%" />
    </label></p>
    <p class="description">Assicurati che inizi con lo stesso prefix del film principale per riutilizzare i cookie firmati CloudFront.</p>
    <p><label>Durata ADS (secondi)<br>
      <input type="number" min="0" name="www_vt_ad_duration" value="<?php echo esc_attr($duration); ?>" style="width:100%" />
    </label></p>
    <p><label>Playlist HLS (es. master.m3u8)<br>
      <input type="text" name="www_vt_ad_playlist" value="<?php echo esc_attr($playlist); ?>" style="width:100%" />
    </label></p>
    <p class="description">Se la playlist è vuota verrà usato <code>master.m3u8</code>. Per una riproduzione fluida su dispositivi iOS e Smart TV assicurati che codec (H.264/AAC) e profili delle varianti coincidano con quelli del film principale.</p>
    <?php
  }

  public function save_meta($post_id) {
    if (!isset($_POST['www_vt_ads_meta_nonce']) || !wp_verify_nonce($_POST['www_vt_ads_meta_nonce'], 'www_vt_ads_meta')) {
      return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }
    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $path = isset($_POST['www_vt_ad_path']) ? www_vt_normalize_path_prefix((string) $_POST['www_vt_ad_path']) : '';
    update_post_meta($post_id, self::META_PATH, $path);

    $duration = isset($_POST['www_vt_ad_duration']) ? absint($_POST['www_vt_ad_duration']) : 0;
    update_post_meta($post_id, self::META_DURATION, $duration);

    $playlist = isset($_POST['www_vt_ad_playlist']) ? sanitize_text_field($_POST['www_vt_ad_playlist']) : '';
    update_post_meta($post_id, self::META_PLAYLIST, $playlist);
  }
}
