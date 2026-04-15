<?php

if (!defined('ABSPATH')) {
    exit;
}

class VWGK_Settings
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Sincronizza verso WP GPT solo quando le impostazioni VWGK cambiano attivamente
        add_action('update_option_vwgk_wp_gpt_token_api', [__CLASS__, 'sync_wp_gpt_credentials']);
        add_action('update_option_vwgk_wp_gpt_user_code', [__CLASS__, 'sync_wp_gpt_credentials']);
    }

    public static function activate(): void
    {
        $defaults = [
            'vwgk_ha_auth_mode'        => 'supervisor_proxy',
            'vwgk_ha_url'              => 'http://homeassistant:8123',
            'vwgk_ha_long_lived_token' => '',
            'vwgk_api_key'             => wp_generate_password(48, false, false),
            'vwgk_wp_gpt_user_code'    => get_option('wp_gpt_user_code', ''),
            'vwgk_wp_gpt_token_api'    => get_option('wp_gpt_api_token', ''),
            'vwgk_login_only_mode'     => '1',
            'vwgk_noindex_mode'        => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key, null) === null) {
                add_option($key, $value);
            }
        }

        self::sync_wp_gpt_credentials();
    }

    public static function add_menu(): void
    {
        add_menu_page(
            __('Virtual World Gate Key', 'virtual-world-gate-key'),
            __('VWGK', 'virtual-world-gate-key'),
            'manage_options',
            'vwgk-settings',
            [__CLASS__, 'render_page'],
            'dashicons-admin-site',
            58
        );
    }

    public static function register_settings(): void
    {
        $settings = [
            'vwgk_ha_auth_mode'        => ['sanitize_callback' => [__CLASS__, 'sanitize_auth_mode']],
            'vwgk_ha_url'              => ['sanitize_callback' => 'esc_url_raw'],
            'vwgk_ha_long_lived_token' => ['sanitize_callback' => 'sanitize_text_field'],
            'vwgk_api_key'             => ['sanitize_callback' => 'sanitize_text_field'],
            'vwgk_wp_gpt_user_code'    => ['sanitize_callback' => 'sanitize_text_field'],
            'vwgk_wp_gpt_token_api'    => ['sanitize_callback' => 'sanitize_text_field'],
            'vwgk_login_only_mode'     => ['sanitize_callback' => [__CLASS__, 'sanitize_bool_string']],
            'vwgk_noindex_mode'        => ['sanitize_callback' => [__CLASS__, 'sanitize_bool_string']],
            'vwgk_ha_dashboard_page_id'=> ['sanitize_callback' => 'absint'],
        ];

        foreach ($settings as $name => $args) {
            register_setting('vwgk_settings', $name, $args);
        }
    }

    public static function sanitize_auth_mode($value): string
    {
        return in_array($value, ['supervisor_proxy', 'long_lived_token'], true)
            ? $value
            : 'supervisor_proxy';
    }

    public static function sanitize_bool_string($value): string
    {
        return (!empty($value) && $value !== '0') ? '1' : '0';
    }

    public static function sync_wp_gpt_credentials(): void
    {
        $token_api = trim((string) get_option('vwgk_wp_gpt_token_api', ''));
        $user_code = trim((string) get_option('vwgk_wp_gpt_user_code', ''));

        if ($token_api !== '' && get_option('wp_gpt_api_token') !== $token_api) {
            update_option('wp_gpt_api_token', $token_api);
        }

        if ($user_code !== '' && get_option('wp_gpt_user_code') !== $user_code) {
            update_option('wp_gpt_user_code', $user_code);
        }
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status = VWGK_HA_Client::connection_summary();
        ?>
        <div class="wrap">
            <h1>Virtual World Gate Key</h1>
            <p>Questo plugin trasforma WordPress in un gateway verso Home Assistant e in un ponte verso le API di WP GPT Automation Pro.</p>

            <table class="widefat striped" style="max-width: 900px; margin: 1rem 0;">
                <tbody>
                    <tr>
                        <th style="width: 240px;">Modalità Home Assistant</th>
                        <td><?php echo esc_html($status['mode']); ?></td>
                    </tr>
                    <tr>
                        <th>Endpoint effettivo</th>
                        <td><code><?php echo esc_html($status['base_url']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Autenticazione disponibile</th>
                        <td><?php echo !empty($status['token_present']) ? 'Sì' : 'No'; ?></td>
                    </tr>
                    <tr>
                        <th>Credenziali WP GPT sincronizzate</th>
                        <td><?php echo (get_option('wp_gpt_api_token') && get_option('wp_gpt_user_code')) ? 'Sì' : 'No'; ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="options.php">
                <?php settings_fields('vwgk_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="vwgk_ha_auth_mode">Modalità connessione HA</label></th>
                        <td>
                            <select id="vwgk_ha_auth_mode" name="vwgk_ha_auth_mode">
                                <?php $mode = get_option('vwgk_ha_auth_mode', 'supervisor_proxy'); ?>
                                <option value="supervisor_proxy" <?php selected($mode, 'supervisor_proxy'); ?>>Supervisor proxy (consigliata dentro Home Assistant)</option>
                                <option value="long_lived_token" <?php selected($mode, 'long_lived_token'); ?>>Endpoint diretto + long-lived token</option>
                            </select>
                            <p class="description">Dentro l'add-on usa preferibilmente il proxy interno <code>http://supervisor/core/api/</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="vwgk_ha_url">Endpoint Home Assistant</label></th>
                        <td>
                            <input class="regular-text code" id="vwgk_ha_url" name="vwgk_ha_url" value="<?php echo esc_attr(get_option('vwgk_ha_url', 'http://homeassistant:8123')); ?>" />
                            <p class="description">Usato solo in modalità <code>long_lived_token</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="vwgk_ha_long_lived_token">Long-lived token Home Assistant</label></th>
                        <td>
                            <input class="regular-text" type="password" id="vwgk_ha_long_lived_token" name="vwgk_ha_long_lived_token" value="<?php echo esc_attr(get_option('vwgk_ha_long_lived_token', '')); ?>" />
                            <p class="description">Token creato nel profilo utente di Home Assistant.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="vwgk_api_key">API key GPT / client esterni</label></th>
                        <td>
                            <input class="regular-text code" id="vwgk_api_key" name="vwgk_api_key" value="<?php echo esc_attr(get_option('vwgk_api_key', '')); ?>" />
                            <p class="description">Inviala come header <code>x-api-key</code> alle rotte <code>/wp-json/vwgk/v1/...</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="vwgk_ha_dashboard_page_id">Pagina Dashboard HA</label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages([
                                'name'             => 'vwgk_ha_dashboard_page_id',
                                'show_option_none' => __('— Nessuna —', 'virtual-world-gate-key'),
                                'option_none_value'=> '0',
                                'selected'         => get_option('vwgk_ha_dashboard_page_id', 0),
                            ]);
                            ?>
                            <p class="description">Seleziona in quale Pagina WordPress forzare il caricamento del template <strong>VWGK Home Assistant Dashboard</strong>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="vwgk_wp_gpt_user_code">WP GPT user_code</label></th>
                        <td>
                            <input class="regular-text code" id="vwgk_wp_gpt_user_code" name="vwgk_wp_gpt_user_code" value="<?php echo esc_attr(get_option('vwgk_wp_gpt_user_code', '')); ?>" />
                            <p class="description">Viene sincronizzato automaticamente nell'opzione <code>wp_gpt_user_code</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="vwgk_wp_gpt_token_api">WP GPT token_api</label></th>
                        <td>
                            <input class="regular-text" type="password" id="vwgk_wp_gpt_token_api" name="vwgk_wp_gpt_token_api" value="<?php echo esc_attr(get_option('vwgk_wp_gpt_token_api', '')); ?>" />
                            <p class="description">Viene sincronizzato automaticamente nell'opzione <code>wp_gpt_api_token</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hardening gateway</th>
                        <td>
                            <label>
                                <input type="checkbox" name="vwgk_login_only_mode" value="1" <?php checked(get_option('vwgk_login_only_mode', '1'), '1'); ?> />
                                Forza homepage/login-only per utenti non autenticati
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="vwgk_noindex_mode" value="1" <?php checked(get_option('vwgk_noindex_mode', '1'), '1'); ?> />
                                Invia header X-Robots-Tag: noindex, nofollow
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Salva configurazione gateway', 'virtual-world-gate-key')); ?>
            </form>

            <h2>Rotte principali</h2>
            <ul>
                <li><code>GET /wp-json/vwgk/v1/status</code></li>
                <li><code>GET /wp-json/vwgk/v1/ha/state?entity_id=light.salone</code></li>
                <li><code>POST /wp-json/vwgk/v1/ha/service</code></li>
                <li><code>POST /wp-json/vwgk/v1/wp-gpt/proxy</code></li>
            </ul>
        </div>
        <?php
    }
}
