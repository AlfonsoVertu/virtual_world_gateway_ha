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

# Determine site URL:
# - Use https if a real domain is configured (reverse proxy terminates SSL)
# - Use http only for localhost/IP testing
if [ "$WP_DOMAIN" = "localhost" ] || [ "$WP_DOMAIN" = "127.0.0.1" ]; then
    WP_SITE_URL="http://$WP_DOMAIN"
else
    WP_SITE_URL="https://$WP_DOMAIN"
fi

echo "[VW Gateway] STARTING..."
echo "[VW Gateway] Site URL: $WP_SITE_URL"

# 0. Bootstrap WordPress core files into /var/www/html
if [ ! -f /var/www/html/wp-includes/version.php ]; then
    echo "[VW Gateway] Bootstrapping WordPress core files..."
    bash /usr/local/bin/docker-entrypoint.sh apache2-foreground &
    WP_BOOT_PID=$!
    RETRIES=30
    while [ ! -f /var/www/html/wp-includes/version.php ] && [ $RETRIES -gt 0 ]; do
        sleep 1
        RETRIES=$((RETRIES - 1))
    done
    kill $WP_BOOT_PID 2>/dev/null || true
    wait $WP_BOOT_PID 2>/dev/null || true
    echo "[VW Gateway] WordPress core files ready."
fi

# 1. Wait for database
echo "[VW Gateway] Waiting for database at $DB_HOST..."
until mysqladmin ping -h "$DB_HOST" --silent; do
    echo "[VW Gateway] Database not ready, waiting..."
    sleep 2
done

# 2. Configure WP-CONFIG (only on first boot)
if [ ! -f /var/www/html/wp-config.php ]; then
    echo "[VW Gateway] Creating wp-config.php..."
    wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --allow-root

    # Security hardening
    wp config set DISALLOW_FILE_EDIT true --raw --allow-root
    wp config set WP_DEBUG false --raw --allow-root

    # IMPORTANT: Do NOT set FORCE_SSL_ADMIN=true here.
    # SSL is terminated by NGINX Proxy Manager, not by this container.
    # Setting it true causes Apache (HTTP-only) to loop-redirect to HTTPS internally -> 502.
    wp config set FORCE_SSL_ADMIN false --raw --allow-root
fi

# 3. Install WordPress if needed
if ! wp core is-installed --allow-root; then
    echo "[VW Gateway] Installing WordPress..."
    wp core install \
        --url="$WP_SITE_URL" \
        --title="$WP_TITLE" \
        --admin_user="$WP_ADMIN" \
        --admin_password="$WP_PASS" \
        --admin_email="$WP_EMAIL" \
        --skip-email \
        --allow-root
fi

# 3b. Always sync siteurl/home in case wp_domain was changed in HA options
wp option update siteurl "$WP_SITE_URL" --allow-root
wp option update home    "$WP_SITE_URL" --allow-root

# 4. Plugin Activation & Sync
echo "[VW Gateway] Syncing plugins and settings..."
wp plugin activate virtual-world-gate-key  --allow-root
wp plugin activate wp-gpt-automation-free  --allow-root

# Sync HA settings into WP options
wp option update vwgk_ha_auth_mode      "$HA_AUTH_MODE" --allow-root
wp option update vwgk_ha_url            "$HA_URL"       --allow-root
wp option update vwgk_ha_long_lived_token "$HA_TOKEN"   --allow-root
wp option update vwgk_api_key           "$VWGK_API_KEY" --allow-root

# Sync Supervisor token if in supervisor_proxy mode
if [ "$HA_AUTH_MODE" == "supervisor_proxy" ]; then
    wp option update vwgk_supervisor_token "$SUPERVISOR_TOKEN" --allow-root
fi

echo "[VW Gateway] Ready! Starting Apache..."
exec apache2-foreground
