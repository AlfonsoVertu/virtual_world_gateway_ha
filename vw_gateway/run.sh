#!/bin/bash
set -e

CONFIG_PATH=/data/options.json

# --- HELPER: Parse JSON Options ---
get_option() {
    cat $CONFIG_PATH | jq -r ".$1"
}

DB_HOST=$(get_option "db_host")
DB_NAME=$(get_option "db_name")
DB_USER=$(get_option "db_user")
DB_PASS=$(get_option "db_pass")

WP_DOMAIN=$(get_option "wp_domain")
WP_TITLE=$(get_option "wp_site_title")
WP_ADMIN=$(get_option "wp_admin_user")
WP_PASS=$(get_option "wp_admin_password")
WP_EMAIL=$(get_option "wp_admin_email")

# HA Integration Settings
HA_AUTH_MODE=$(get_option "ha_auth_mode")
HA_URL=$(get_option "ha_url")
HA_TOKEN=$(get_option "ha_long_lived_token")
VWGK_API_KEY=$(get_option "vwgk_api_key")

echo "[VW Gateway] STARTING..."

# 1. Wait for database
echo "[VW Gateway] Waiting for database at $DB_HOST..."
until mysqladmin ping -h "$DB_HOST" --silent; do
    echo "[VW Gateway] Database not ready, waiting..."
    sleep 2
done

# 2. Configure WP-CONFIG
if [ ! -f /var/www/html/wp-config.php ]; then
    echo "[VW Gateway] Creating wp-config.php..."
    wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --allow-root
    
    # Add hardening constants
    wp config set FORCE_SSL_ADMIN true --raw --allow-root
    wp config set DISALLOW_FILE_EDIT true --raw --allow-root
    wp config set WP_DEBUG false --raw --allow-root
fi

# 3. Install WordPress if needed
if ! wp core is-installed --allow-root; then
    echo "[VW Gateway] Installing WordPress..."
    wp core install --url="http://$WP_DOMAIN" --title="$WP_TITLE" --admin_user="$WP_ADMIN" --admin_password="$WP_PASS" --admin_email="$WP_EMAIL" --skip-email --allow-root
fi

# 4. Plugin Activation & Sync
echo "[VW Gateway] Syncing plugins and settings..."
wp plugin activate virtual-world-gate-key --allow-root

# Sync HA settings into WP options for the plugin
wp option update vwgk_ha_auth_mode "$HA_AUTH_MODE" --allow-root
wp option update vwgk_ha_url "$HA_URL" --allow-root
wp option update vwgk_ha_long_lived_token "$HA_TOKEN" --allow-root
wp option update vwgk_api_key "$VWGK_API_KEY" --allow-root

# Sync internal HA token if in supervisor mode
if [ "$HA_AUTH_MODE" == "supervisor_proxy" ]; then
    # Supervisor Token is injected by HA at runtime
    wp option update vwgk_supervisor_token "$SUPERVISOR_TOKEN" --allow-root
fi

echo "[VW Gateway] Ready! Starting Apache..."
exec apache2-foreground
