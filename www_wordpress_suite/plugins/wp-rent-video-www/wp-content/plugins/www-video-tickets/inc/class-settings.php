<?php
if (!defined('ABSPATH')) exit;

class WWW_VT_Settings {
  public function init() {
    add_action('admin_menu', [$this,'menu']);
    add_action('admin_init', [$this,'register']);
    add_action('admin_notices', [$this,'domain_notice']);
    add_action('admin_notices', [$this,'credentials_notice']);
  }

  public function menu() {
    add_options_page('WWW Video Tickets','WWW Video Tickets','manage_options','www-vt',[$this,'page']);
  }

  public function register() {
    register_setting(WWW_VT_OPT, WWW_VT_OPT);
    add_settings_section('main','CloudFront & Sicurezza', function(){
      $guide = <<<HTML
<div class="www-vt-guide">
  <h2>Setup "film a X visioni / Y ore"</h2>
  <p>Guida cronologica per pubblicare un titolo cifrato con S3 + CloudFront e servire la chiave AES tramite il plugin.</p>

  <h3>0) Prerequisiti rapidi</h3>
  <ul>
    <li>Dominio principale: <code>miodominio.it</code>; sottodominio video: <code>video.miodominio.it</code>.</li>
    <li>WordPress operativo con plugin <strong>WWW Video Tickets</strong> attivo.</li>
    <li>Accesso alla console AWS.</li>
  </ul>

  <h3>1) AWS: S3 privato per i video</h3>
  <ol>
    <li><em>Create bucket</em> → nome <code>mio-bucket-video</code>; <em>Block all public access</em>: ON; versioning facoltativo.</li>
    <li>Carica i file HLS nella struttura:<br/><code>s3://mio-bucket-video/videos/123/master.m3u8</code><br/><code>s3://mio-bucket-video/videos/123/seg_00001.ts</code> …</li>
    <li>Lascia tutto privato. <strong>Non</strong> caricare la chiave AES nel bucket.</li>
  </ol>

  <h3>2) AWS: CloudFront con OAC</h3>
  <h4>2.1 Crea la distribuzione</h4>
  <ol>
    <li><em>Create Distribution</em> con origin il bucket S3 e <em>Origin access</em> = Origin Access Control (crea nuovo OAC).</li>
    <li>Behaviour predefinito (temporaneo): viewer protocol policy = Redirect HTTP → HTTPS; metodi = GET, HEAD; cache policy = Managed <em>CachingOptimized</em>.</li>
    <li>Crea la distribuzione e applica la bucket policy proposta da CloudFront in S3 → Permissions → Bucket policy.</li>
  </ol>

  <h4>2.2 Alternate Domain Name + certificato</h4>
  <ol>
    <li>In CloudFront → Settings: Alternate domain name = <code>video.miodominio.it</code>.</li>
    <li>Richiedi il certificato in ACM (regione <code>us-east-1</code>) per <code>video.miodominio.it</code>, valida via DNS e selezionalo in CloudFront.</li>
    <li>Aggiungi in DNS: <code>video.miodominio.it. CNAME dXXXXXXXX.cloudfront.net.</code></li>
  </ol>

  <h4>2.3 Behaviors separati</h4>
  <ul>
    <li><strong>Behaviour /videos/</strong>: path pattern <code>/videos/*</code>; origin = bucket S3; Redirect to HTTPS; Restrict viewer access = Yes (Signed Cookies) con Key Group; cache policy personalizzate (playlist TTL 5–30s, segmenti 1–7 giorni); opzionale Origin Shield ON.</li>
    <li><strong>Behaviour /keys/</strong>: path pattern <code>/keys/*</code>; origin = WordPress (<code>www.miodominio.it</code> o dominio WP); viewer protocol = HTTPS only; cache policy <em>No cache</em> (TTL 0); inoltra tutti i cookie (incluso <code>CF-Auth</code>) e, se serve, gli header.</li>
  </ul>

  <h3>3) CloudFront Signed Cookies</h3>
  <ol>
    <li>In CloudFront → Public keys → <em>Add public key</em> (userai la chiave RSA del passo 5).</li>
    <li>CloudFront → Key groups → <em>Create key group</em> e associa la public key.</li>
    <li>Nel behaviour <code>/videos/*</code> imposta <em>Restrict viewer access</em> = Key group e annota il <strong>Key Pair ID</strong>.</li>
  </ol>

  <h3>4) Conversione HLS + AES-128 (locale)</h3>
  <ol>
    <li>Genera la chiave AES:<pre><code>openssl rand 16 &gt; key.bin
xxd -p -c 32 key.bin  # copia i 32 caratteri (Key HEX)</code></pre></li>
    <li>Prepara <code>key_info.txt</code> con tre righe:<pre><code>https://video.miodominio.it/keys/123.key
/ABSOLUTE/PATH/key.bin
/ABSOLUTE/PATH/videos/123/</code></pre></li>
    <li>Transcodifica con FFmpeg (bitrate singolo robusto):
      <pre><code>ffmpeg -i film.mp4 \
  -c:v libx264 -preset medium -b:v 6000k -maxrate 6500k -bufsize 13000k \
  -c:a aac -b:a 128k \
  -hls_time 4 -hls_playlist_type vod \
  -hls_key_info_file key_info.txt \
  -hls_segment_filename "videos/123/seg_%05d.ts" \
  "videos/123/master.m3u8"</code></pre>
      <em>Opzionale</em>: chiedi lo script multi-bitrate (1080p/720p/480p) con master playlist.</li>
    <li>Carica la cartella <code>videos/123/</code> su S3 mantenendo la struttura. Non caricare <code>key.bin</code> né <code>key_info.txt</code>.</li>
  </ol>

  <h3>5) Chiavi RSA per i Signed Cookies</h3>
  <pre><code>openssl genrsa -out cf_private.pem 2048
openssl rsa -in cf_private.pem -pubout -out cf_public.pem</code></pre>
  <p>Carica <code>cf_public.pem</code> in CloudFront → Public keys e annota il Key Pair ID. <code>cf_private.pem</code> servirà al plugin.</p>

  <h3>6) Configurazione plugin (questa schermata)</h3>
  <ul>
    <li><strong>CloudFront Domain (CNAME)</strong>: <code>https://video.miodominio.it</code>.</li>
    <li><strong>Key Pair ID</strong>: l’ID della public key nel Key Group.</li>
    <li><strong>Private Key (PEM)</strong>: inserisci la chiave privata oppure definisci in <code>wp-config.php</code> <code>define('WWW_VT_PRIVATE_KEY', '-----BEGIN...');</code></li>
    <li><strong>TTL cookie (min)</strong>: 120 (consigliato 60–240).</li>
    <li><strong>JWT secret (HS256)</strong>: genera con <code>openssl rand -base64 32</code> (puoi definirlo in <code>wp-config.php</code> con <code>WWW_VT_JWT_SECRET</code>).</li>
    <li>Motivo del CNAME sullo stesso dominio: solo così il browser accetta i cookie impostati dal tuo sito.</li>
  </ul>

  <h3>7) WooCommerce: scheda prodotto</h3>
  <ul>
    <li><strong>Max visioni</strong>: es. 3.</li>
    <li><strong>Finestra (ore)</strong>: es. 48.</li>
    <li><strong>Path prefix HLS</strong>: <code>/videos/123/</code>.</li>
    <li><strong>Key HEX</strong>: i 32 caratteri esadecimali di <code>key.bin</code>.</li>
    <li><strong>Key URI</strong>: <code>/keys/123.key</code>.</li>
  </ul>

  <h3>8) Pagina di visione</h3>
  <p>Nell’area riservata inserisci lo shortcode:<br/><code>[video_watch product_id="123" playlist="/videos/123/master.m3u8"]</code></p>
  <p>Il plugin emette CloudFront Signed Cookies e il cookie <code>CF-Auth</code> valido per la finestra impostata.</p>

  <h3>9) Checklist end-to-end</h3>
  <ul>
    <li><strong>Cookie</strong>: in DevTools → Cookies su <code>video.miodominio.it</code> devono comparire <code>CloudFront-Policy</code>, <code>CloudFront-Signature</code>, <code>CloudFront-Key-Pair-Id</code>, <code>CF-Auth</code>.</li>
    <li><strong>Network</strong>: <code>POST /wp-json/www-vt/v1/view/start</code> → 200; GET su playlist/segmenti → 200; GET <code>/keys/123.key</code> → 200 (16 byte).</li>
    <li><strong>Sicurezza</strong>: senza cookie l’accesso a <code>/videos/123/master.m3u8</code> deve dare <em>AccessDenied</em>; <code>/keys/123.key</code> → 403.</li>
    <li><strong>Cache</strong>: playlist TTL basso, segmenti TTL alto, <code>/keys/*</code> no cache.</li>
  </ul>

  <h3>10) Parametri consigliati</h3>
  <ul>
    <li>Risoluzione massima sensata: 1080p.</li>
    <li>Bitrate indicativi: 1080p 6–8&nbsp;Mbit/s; 720p 3–4; 480p 1.5–2.</li>
    <li>Segmenti da 2–4 secondi.</li>
    <li>Costo stimato: ~0,18&nbsp;$ per view (2&nbsp;GB a 0,09&nbsp;$/GB).</li>
  </ul>

  <h3>11) Troubleshooting veloce</h3>
  <ul>
    <li><strong>Playlist/segmenti 403</strong>: controlla OAC, bucket policy, behaviour <code>/videos/*</code> con Signed Cookies e cookie presenti.</li>
    <li><strong>Chiave 403</strong>: verifica cookie <code>CF-Auth</code>, Key HEX corretta, licenza attiva, behaviour <code>/keys/*</code> verso WordPress con forward cookie.</li>
    <li><strong>Cookie mancanti</strong>: il CNAME deve essere sottodominio del sito e certificato ACM corretto.</li>
    <li><strong>Buffering</strong>: abbassa il bitrate o aggiungi varianti più leggere.</li>
  </ul>

  <h3>12) Hardening &amp; Ops (facoltativo)</h3>
  <ul>
    <li>Rate limit su <code>/keys/*</code> (WAF o patch del plugin).</li>
    <li>Abilita log su CloudFront/S3 e logging WordPress per l’endpoint chiave.</li>
    <li>Watermark dinamico nel player (ID ordine).</li>
    <li>Valuta AWS KMS per proteggere la chiave AES.</li>
  </ul>
</div>
HTML;
      echo $guide;
    }, WWW_VT_OPT);

    $fields = [
      'cloudfront_domain'  => 'CloudFront Domain (CNAME, es. video.tuodominio.it)',
      'key_pair_id'        => 'Key Pair ID (Key Group CloudFront)',
      'private_key_pem'    => 'Private Key (PEM) — preferisci costante WWW_VT_PRIVATE_KEY',
      'cookie_ttl_minutes' => 'TTL cookie (minuti)',
      'jwt_secret'         => 'JWT secret (HS256)',
    ];
    foreach ($fields as $k=>$label) {
      add_settings_field($k, $label, [$this,'render_input'], WWW_VT_OPT, 'main', ['key'=>$k]);
    }

    register_setting(WWW_VT_OPT, 'www_vt_consumption_threshold_seconds', ['type'=>'integer','sanitize_callback'=>'absint','default'=>30]);
    register_setting(WWW_VT_OPT, 'www_vt_heartbeat_interval_ms', ['type'=>'integer','sanitize_callback'=>'absint','default'=>10000]);
    register_setting(WWW_VT_OPT, 'www_vt_position_save_interval_ms', ['type'=>'integer','sanitize_callback'=>'absint','default'=>60000]);
    register_setting(WWW_VT_OPT, 'www_vt_ads_interval_seconds', ['type'=>'integer','sanitize_callback'=>'absint','default'=>0]);
    register_setting(WWW_VT_OPT, 'www_vt_ads_duration_seconds', ['type'=>'integer','sanitize_callback'=>'absint','default'=>0]);

    add_settings_field('www_vt_consumption_threshold_seconds', 'Soglia consumo progressi (secondi)', [$this,'render_progress_field'], WWW_VT_OPT, 'main', [
      'option' => 'www_vt_consumption_threshold_seconds',
      'default' => 30,
      'min' => 1,
      'description' => 'Delta massimo di secondi accettato in un singolo aggiornamento del player.',
    ]);
    add_settings_field('www_vt_heartbeat_interval_ms', 'Heartbeat progressi (ms)', [$this,'render_progress_field'], WWW_VT_OPT, 'main', [
      'option' => 'www_vt_heartbeat_interval_ms',
      'default' => 10000,
      'min' => 1000,
      'description' => 'Frequenza dell’aggiornamento regolare verso l’endpoint /view/progress.',
    ]);
    add_settings_field('www_vt_position_save_interval_ms', 'Salvataggio posizione (ms)', [$this,'render_progress_field'], WWW_VT_OPT, 'main', [
      'option' => 'www_vt_position_save_interval_ms',
      'default' => 60000,
      'min' => 1000,
      'description' => 'Intervallo per il salvataggio forzato della posizione di riproduzione.',
    ]);
    add_settings_field('www_vt_ads_interval_seconds', 'Intervallo ADS (s)', [$this,'render_progress_field'], WWW_VT_OPT, 'main', [
      'option' => 'www_vt_ads_interval_seconds',
      'default' => 0,
      'min' => 0,
      'description' => 'Ogni quanti secondi di visione del film inserire una pausa pubblicitaria.',
    ]);
    add_settings_field('www_vt_ads_duration_seconds', 'Durata ADS (s)', [$this,'render_progress_field'], WWW_VT_OPT, 'main', [
      'option' => 'www_vt_ads_duration_seconds',
      'default' => 0,
      'min' => 0,
      'description' => 'Durata complessiva consigliata delle creatività video da riprodurre in ciascuna pausa.',
    ]);
  }

  public function render_input($args) {
    $key = $args['key'];
    $opt = www_vt_opts();
    $value = $opt[$key] ?? '';
    if ($key==='private_key_pem') {
      printf('<textarea name="%s[%s]" rows="6" cols="80">%s</textarea>', WWW_VT_OPT, esc_attr($key), esc_textarea($value));
    } else {
      printf('<input type="text" style="width:520px" name="%s[%s]" value="%s"/>', WWW_VT_OPT, esc_attr($key), esc_attr($value));
    }
  }

  public function render_progress_field($args) {
    $option = $args['option'];
    $default = isset($args['default']) ? (int) $args['default'] : 0;
    $value = (int) get_option($option, $default);
    $min = isset($args['min']) ? (int) $args['min'] : null;
    printf(
      '<input type="number" name="%s" value="%d" %s />',
      esc_attr($option),
      $value,
      $min !== null ? 'min="'.esc_attr($min).'"' : ''
    );
    if (!empty($args['description'])) {
      printf('<p class="description">%s</p>', esc_html($args['description']));
    }
  }

  public function page() {
    echo '<div class="wrap"><h1>WWW Video Tickets</h1><form method="post" action="options.php">';
    settings_fields(WWW_VT_OPT);
    do_settings_sections(WWW_VT_OPT);
    submit_button();
    echo '</form></div>';
  }

  public function domain_notice() {
    if (!current_user_can('manage_options')) return;
    $cf = www_vt_cf_host();
    if (!$cf) return;
    $site = parse_url(home_url(), PHP_URL_HOST);
    if (!$site) return;

    // Se CloudFront non è un sottodominio del sito, avvisa.
    $is_sub = substr($cf, -strlen($site)) === $site;
    if (!$is_sub) {
      echo '<div class="notice notice-warning"><p><strong>WWW Video Tickets:</strong> usa un CNAME CloudFront sotto il dominio del sito (es. <code>video.'.$site.'</code>) per poter impostare i cookie della CDN dal frontend.</p></div>';
    }
  }

  public function credentials_notice() {
    if (!current_user_can('manage_options')) return;
    $opts = www_vt_opts();
    $messages = [];

    if (empty($opts['key_pair_id'])) {
      $messages[] = 'CloudFront Key Pair ID mancante.';
    }

    $pk = defined('WWW_VT_PRIVATE_KEY') ? WWW_VT_PRIVATE_KEY : ($opts['private_key_pem'] ?? '');
    if (!is_string($pk) || trim($pk) === '') {
      $messages[] = 'Private Key PEM mancante (definisci WWW_VT_PRIVATE_KEY o inseriscila nelle impostazioni).';
    }

    if (empty($opts['jwt_secret'])) {
      $messages[] = 'JWT secret mancante.';
    }

    if (!empty($messages)) {
      echo '<div class="notice notice-error"><p><strong>WWW Video Tickets:</strong><br/>' . implode('<br/>', array_map('esc_html', $messages)) . '</p></div>';
    }
  }
}
