<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Admin_Licenses {
  public function init() {
    add_action('admin_menu',[$this,'menu']);
    add_action('admin_post_www_vt_update_license',[$this,'update']);
    add_action('admin_post_www_vt_create_license',[$this,'create']);
  }

  public function menu() {
    add_menu_page('Video Tickets','Video Tickets','manage_woocommerce','www-vt-licenses',[$this,'page'],'dashicons-tickets',56);
    add_submenu_page('www-vt-licenses','Licenze','Licenze','manage_woocommerce','www-vt-licenses',[$this,'page']);
    add_submenu_page('www-vt-licenses','Impostazioni','Impostazioni','manage_options','www-vt-settings',function(){
      wp_safe_redirect(admin_url('options-general.php?page=www-vt')); exit;
    });
  }

  public function page() {
    if (!current_user_can('manage_woocommerce')) wp_die('No perms');
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB;

    $q = trim((string)($_GET['q']??'')); $paged=max(1,(int)($_GET['paged']??1)); $per=25; $off=($paged-1)*$per;
    $where='1=1'; $args=[];
    if ($q!==''){ $where.=" AND (user_id=%d OR product_id=%d OR order_id=%d)"; $args=[(int)$q,(int)$q,(int)$q]; }

    $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE $where", ...$args));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", ...array_merge($args,[$per,$off])), ARRAY_A);

    $base = admin_url('admin.php?page=www-vt-licenses');
    $msg = sanitize_text_field($_GET['www_vt_msg'] ?? '');
    ?>
    <div class="wrap">
      <h1>Licenze</h1>
      <?php if ($msg): ?>
        <?php if ($msg === 'created'): ?>
          <div class="notice notice-success is-dismissible"><p>Licenza creata con successo.</p></div>
        <?php elseif ($msg === 'invalid_user'): ?>
          <div class="notice notice-error"><p>Utente non valido.</p></div>
        <?php elseif ($msg === 'invalid_product'): ?>
          <div class="notice notice-error"><p>Prodotto non valido.</p></div>
        <?php elseif ($msg === 'invalid_views'): ?>
          <div class="notice notice-error"><p>Numero di visioni non valido.</p></div>
        <?php elseif ($msg === 'invalid_seconds'): ?>
          <div class="notice notice-error"><p>Secondi residui non validi.</p></div>
        <?php elseif ($msg === 'invalid_path'): ?>
          <div class="notice notice-error"><p>Path non valido.</p></div>
        <?php else: ?>
          <div class="notice notice-error"><p>Si è verificato un errore.</p></div>
        <?php endif; ?>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="max-width:780px;margin:20px 0;padding:20px;">
        <h2 style="margin-top:0;">Aggiungi licenza</h2>
        <?php wp_nonce_field('www_vt_create_license'); ?>
        <input type="hidden" name="action" value="www_vt_create_license">
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label for="www-vt-user-id">Utente (ID)</label></th>
              <td><input required type="number" min="1" name="user_id" id="www-vt-user-id" class="regular-text" style="max-width:200px;"></td>
            </tr>
            <tr>
              <th scope="row"><label for="www-vt-product-id">Prodotto (ID)</label></th>
              <td><input required type="number" min="1" name="product_id" id="www-vt-product-id" class="regular-text" style="max-width:200px;"></td>
            </tr>
            <tr>
              <th scope="row"><label for="www-vt-order-id">Ordine (ID)</label></th>
              <td><input type="number" min="0" name="order_id" id="www-vt-order-id" class="regular-text" style="max-width:200px;"> <p class="description">Opzionale.</p></td>
            </tr>
            <tr>
              <th scope="row"><label for="www-vt-views">Visioni residue</label></th>
              <td><input required type="number" min="1" name="remaining_views" id="www-vt-views" class="regular-text" style="max-width:200px;" value="1"></td>
            </tr>
            <tr>
              <th scope="row"><label for="www-vt-seconds">Secondi residui</label></th>
              <td><input required type="number" min="0" name="remaining_seconds" id="www-vt-seconds" class="regular-text" style="max-width:200px;" value="3600"> <p class="description">Imposta i secondi disponibili (minuti acquistati × 60).</p></td>
            </tr>
            <tr>
              <th scope="row"><label for="www-vt-expires">Scadenza</label></th>
              <td><input type="datetime-local" name="expires_at" id="www-vt-expires" class="regular-text" style="max-width:240px;"><p class="description">Lascia vuoto per scadere tra 24 ore.</p></td>
            </tr>
            <tr>
              <th scope="row"><label for="www-vt-path">Path</label></th>
              <td><input required type="text" name="path_prefix" id="www-vt-path" class="regular-text"> <p class="description">Esempio: /video/promo/</p></td>
            </tr>
          </tbody>
        </table>
        <p class="submit"><button type="submit" class="button button-primary">Aggiungi licenza</button></p>
      </form>
      <form method="get" style="margin:10px 0">
        <input type="hidden" name="page" value="www-vt-licenses">
        <input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="user_id / product_id / order_id">
        <button class="button">Cerca</button>
      </form>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th>User</th><th>Product</th><th>Order</th><th>Views</th><th>Seconds</th><th>Expires</th><th>Path</th><th>Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo (int)$r['user_id']; ?></td>
            <td><?php echo (int)$r['product_id']; ?></td>
            <td><?php echo (int)$r['order_id']; ?></td>
            <td><?php echo (int)$r['remaining_views']; ?></td>
            <td><?php echo (int)$r['remaining_seconds']; ?></td>
            <td><?php echo esc_html($r['expires_at']); ?></td>
            <td><code><?php echo esc_html($r['path_prefix']); ?></code></td>
            <td>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <?php wp_nonce_field('www_vt_update_license_'.$r['id']); ?>
                <input type="hidden" name="action" value="www_vt_update_license">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="number" name="remaining_views" value="<?php echo (int)$r['remaining_views']; ?>" style="width:80px">
                <input type="number" name="remaining_seconds" value="<?php echo (int)$r['remaining_seconds']; ?>" style="width:120px" min="0">
                <input type="datetime-local" name="expires_at" value="<?php echo esc_attr(self::to_local($r['expires_at'])); ?>">
                <button class="button button-primary">Salva</button>
              </form>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:6px">
                <?php wp_nonce_field('www_vt_update_license_'.$r['id']); ?>
                <input type="hidden" name="action" value="www_vt_update_license">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="expire_now" value="1">
                <button class="button">Scade ora</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php
      $pages=max(1,(int)ceil($total/$per));
      if ($pages>1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for($i=1;$i<=$pages;$i++){
          $url = esc_url(add_query_arg(['paged'=>$i], $base.($q!==''?'&q='.urlencode($q):'')));
          $cls = $i===$paged ? 'button button-primary' : 'button';
          echo '<a class="'.$cls.'" href="'.$url.'">'.$i.'</a> ';
        }
        echo '</div></div>';
      } ?>
    </div>
    <?php
  }

  private static function to_local($gmt) {
    try {
      $utc = new \DateTime($gmt, new \DateTimeZone('UTC'));
      $tz_name = get_option('timezone_string') ?: 'UTC';
      $tz = new \DateTimeZone($tz_name);
      $utc->setTimezone($tz);
      return $utc->format('Y-m-d\TH:i');
    } catch (\Exception $e) {
      $ts = strtotime($gmt.' UTC') ?: time();
      return date('Y-m-d\TH:i', $ts);
    }
  }

  public function update() {
    if (!current_user_can('manage_woocommerce')) wp_die('No perms');
    $id=(int)($_POST['id']??0); if(!$id) wp_die('Invalid');
    check_admin_referer('www_vt_update_license_'.$id);
    global $wpdb; $t=$wpdb->prefix.WWW_VT_DB;

    if (!empty($_POST['expire_now'])) {
      $wpdb->update($t,[
        'expires_at'=>gmdate('Y-m-d H:i:s'),
        'remaining_seconds'=>0,
        'updated_at'=>gmdate('Y-m-d H:i:s')
      ],['id'=>$id]);
    } else {
      $rem=max(0,(int)($_POST['remaining_views']??0));
      $secs=max(0,(int)($_POST['remaining_seconds']??0));
      $loc=sanitize_text_field($_POST['expires_at']??'');
      $gmt = self::to_gmt($loc);
      $wpdb->update($t,[
        'remaining_views'=>$rem,
        'remaining_seconds'=>$secs,
        'expires_at'=>$gmt,
        'updated_at'=>gmdate('Y-m-d H:i:s')
      ],['id'=>$id]);
    }
    wp_safe_redirect(admin_url('admin.php?page=www-vt-licenses')); exit;
  }

  public function create() {
    if (!current_user_can('manage_woocommerce')) wp_die('No perms');
    check_admin_referer('www_vt_create_license');

    $user_id = (int)($_POST['user_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $order_id = (int)($_POST['order_id'] ?? 0);
    $remaining = max(0,(int)($_POST['remaining_views'] ?? 0));
    $seconds = max(0,(int)($_POST['remaining_seconds'] ?? 0));
    $path = sanitize_text_field($_POST['path_prefix'] ?? '');
    $expires_local = sanitize_text_field($_POST['expires_at'] ?? '');

    if (!$user_id || !get_user_by('id', $user_id)) {
      $this->redirect_with_message('invalid_user');
    }
    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
      $this->redirect_with_message('invalid_product');
    }
    if ($remaining <= 0) {
      $this->redirect_with_message('invalid_views');
    }
    if ($seconds <= 0) {
      $this->redirect_with_message('invalid_seconds');
    }

    $normalized_path = www_vt_normalize_path_prefix($path);
    if ($normalized_path === '') {
      $this->redirect_with_message('invalid_path');
    }

    $expires_gmt = $expires_local ? self::to_gmt($expires_local) : gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);

    WWW_VT_License_Store::create([
      'user_id' => $user_id,
      'product_id' => $product_id,
      'order_id' => $order_id,
      'remaining_views' => $remaining,
      'remaining_seconds' => $seconds,
      'expires_at' => $expires_gmt,
      'path_prefix' => $normalized_path,
    ]);

    $this->redirect_with_message('created');
  }

  private function redirect_with_message(string $msg) {
    $base = admin_url('admin.php?page=www-vt-licenses');
    wp_safe_redirect(add_query_arg('www_vt_msg', $msg, $base));
    exit;
  }

  private static function to_gmt(string $loc): string {
    if ($loc === '') {
      return gmdate('Y-m-d H:i:s');
    }
    $tz_name = get_option('timezone_string') ?: 'UTC';
    try {
      $tz = new \DateTimeZone($tz_name);
      $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $loc, $tz);
      if ($dt === false) {
        $timestamp = strtotime($loc) ?: time();
        return gmdate('Y-m-d H:i:s', $timestamp);
      }
      $dt->setTimezone(new \DateTimeZone('UTC'));
      return $dt->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
      $timestamp = strtotime($loc) ?: time();
      return gmdate('Y-m-d H:i:s', $timestamp);
    }
  }
}
