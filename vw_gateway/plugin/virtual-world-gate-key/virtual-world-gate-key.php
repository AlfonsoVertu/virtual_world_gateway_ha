<?php
/**
 * Plugin Name: Virtual World Gate Key
 * Plugin URI: https://example.com/virtual-world-gate-key
 * Description: Middleware WordPress per Home Assistant e ponte verso WP GPT Automation Pro.
 * Version: 1.0.2
 * Author: OpenAI
 * Text Domain: virtual-world-gate-key
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VWGK_VERSION', '1.0.2');
define('VWGK_PATH', plugin_dir_path(__FILE__));
define('VWGK_URL', plugin_dir_url(__FILE__));
define('VWGK_PLUGIN_DIR', VWGK_PATH); // Fix for VWGK_Auth_Enforcer
define('VWGK_ASSETS_URL', VWGK_URL . 'assets/');

require_once VWGK_PATH . 'includes/class-vwgk-settings.php';
require_once VWGK_PATH . 'includes/class-vwgk-ha-client.php';
require_once VWGK_PATH . 'includes/class-vwgk-rest.php';
require_once VWGK_PATH . 'includes/class-vwgk-hardening.php';
require_once VWGK_PATH . 'includes/class-vwgk-ha-api.php';
require_once VWGK_PATH . 'includes/class-vwgk-ha-card-factory.php';
require_once VWGK_PATH . 'includes/class-vwgk-shortcodes.php';
require_once VWGK_PATH . 'includes/class-vwgk-auth-enforcer.php';
require_once VWGK_PATH . 'includes/class-vwgk-dashboard-metabox.php';
require_once VWGK_PATH . 'includes/class-vwgk-entity-manager.php';

register_activation_hook(__FILE__, ['VWGK_Settings', 'activate']);

add_action('plugins_loaded', function () {
    // Forza le impostazioni del token fornito dall'utente per risolvere il 401
    update_option('vwgk_ha_auth_mode', 'long_lived_token');
    update_option('vwgk_ha_url', 'http://192.168.1.56:8123');
    update_option('vwgk_ha_long_lived_token', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiI2MjgyNGE4NjIzYjA0YTQ2YjkwM2VhY2I1NmZjODUwOSIsImlhdCI6MTc3NDI2OTUxMiwiZXhwIjoyMDg5NjI5NTEyfQ.BeGzMdf5QdZQBceTS45BDOrjoC_pNvpUnqKPoXEDZq0');
    // Pulisce la cache per ricaricare subito i dati
    delete_transient('vwgk_ha_entities_cache');

    VWGK_Settings::init();
    VWGK_Hardening::init();
    VWGK_REST::init();
    VWGK_Shortcodes::init();
    VWGK_Auth_Enforcer::init();
    VWGK_Dashboard_Metabox::init();
    VWGK_Entity_Manager::init();
});
