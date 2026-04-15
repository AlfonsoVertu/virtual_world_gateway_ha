<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Admin_Ads_Report {
  public function init() {
    add_action('admin_menu', [$this, 'menu']);
  }

  public function menu() {
    add_submenu_page(
      'www-vt-licenses',
      'Report ADS',
      'Report ADS',
      'manage_woocommerce',
      'www-vt-ads-report',
      [$this, 'page']
    );
  }

  public function page() {
    if (!current_user_can('manage_woocommerce')) {
      wp_die('Permessi insufficienti');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'www_vt_ads_views';

    $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
    $ad_id = isset($_GET['ad_id']) ? (int) $_GET['ad_id'] : 0;
    $from = isset($_GET['from']) ? sanitize_text_field((string) $_GET['from']) : '';
    $to = isset($_GET['to']) ? sanitize_text_field((string) $_GET['to']) : '';
    $paged = max(1, (int) ($_GET['paged'] ?? 1));
    $per_page = 25;
    $offset = ($paged - 1) * $per_page;

    $where = ['1=1'];
    $args = [];

    if ($product_id > 0) {
      $where[] = 'product_id = %d';
      $args[] = $product_id;
    }

    if ($ad_id > 0) {
      $where[] = 'ad_id = %d';
      $args[] = $ad_id;
    }

    if ($from !== '') {
      $from_time = strtotime($from . ' 00:00:00');
      if ($from_time) {
        $where[] = 'viewed_at >= %s';
        $args[] = gmdate('Y-m-d H:i:s', $from_time);
      }
    }

    if ($to !== '') {
      $to_time = strtotime($to . ' 23:59:59');
      if ($to_time) {
        $where[] = 'viewed_at <= %s';
        $args[] = gmdate('Y-m-d H:i:s', $to_time);
      }
    }

    $sql_where = implode(' AND ', $where);

    $sql_count = "SELECT COUNT(*) FROM $table WHERE $sql_where";
    if (!empty($args)) {
      $sql_count = $wpdb->prepare($sql_count, $args);
    }
    $total = (int) $wpdb->get_var($sql_count);

    $sql_rows = "SELECT * FROM $table WHERE $sql_where ORDER BY viewed_at DESC LIMIT %d OFFSET %d";
    $rows_args = array_merge($args, [$per_page, $offset]);
    $sql_rows = $wpdb->prepare($sql_rows, $rows_args);
    $rows = $wpdb->get_results($sql_rows, ARRAY_A);

    $pages = max(1, (int) ceil($total / $per_page));
    $base_url = admin_url('admin.php?page=www-vt-ads-report');

    ?>
    <div class="wrap">
      <h1>Report visualizzazioni ADS</h1>
      <form method="get" class="www-vt-filters" style="margin:20px 0;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <input type="hidden" name="page" value="www-vt-ads-report">
        <p>
          <label>Prodotto (ID)<br>
            <input type="number" name="product_id" value="<?php echo esc_attr($product_id); ?>" min="0" style="width:120px;">
          </label>
        </p>
        <p>
          <label>ADS (ID)<br>
            <input type="number" name="ad_id" value="<?php echo esc_attr($ad_id); ?>" min="0" style="width:120px;">
          </label>
        </p>
        <p>
          <label>Dal (YYYY-MM-DD)<br>
            <input type="date" name="from" value="<?php echo esc_attr($from); ?>">
          </label>
        </p>
        <p>
          <label>Al (YYYY-MM-DD)<br>
            <input type="date" name="to" value="<?php echo esc_attr($to); ?>">
          </label>
        </p>
        <p>
          <button class="button button-primary">Filtra</button>
        </p>
      </form>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>ADS</th>
            <th>Prodotto</th>
            <th>Utente</th>
            <th>Licenza</th>
            <th>Sessione</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7">Nessuna visualizzazione trovata.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo (int) $row['id']; ?></td>
              <td>
                <?php if ($row['ad_id']): ?>
                  <strong>#<?php echo (int) $row['ad_id']; ?></strong><br>
                  <span><?php echo esc_html(get_the_title((int) $row['ad_id'])); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['product_id']): ?>
                  <strong>#<?php echo (int) $row['product_id']; ?></strong><br>
                  <span><?php echo esc_html(get_the_title((int) $row['product_id'])); ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo (int) $row['user_id']; ?></td>
              <td><?php echo (int) $row['license_id']; ?></td>
              <td><code><?php echo esc_html($row['session_id']); ?></code></td>
              <td><?php echo esc_html(get_date_from_gmt($row['viewed_at'], 'Y-m-d H:i:s')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      <?php if ($pages > 1): ?>
        <div class="tablenav">
          <div class="tablenav-pages">
            <?php for ($i = 1; $i <= $pages; $i++):
              $url = add_query_arg([
                'page' => 'www-vt-ads-report',
                'product_id' => $product_id,
                'ad_id' => $ad_id,
                'from' => $from,
                'to' => $to,
                'paged' => $i,
              ], $base_url);
              $cls = $i === $paged ? 'button button-primary' : 'button';
            ?>
              <a class="<?php echo esc_attr($cls); ?>" href="<?php echo esc_url($url); ?>"><?php echo (int) $i; ?></a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }
}
