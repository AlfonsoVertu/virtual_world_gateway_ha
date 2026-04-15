#!/usr/bin/env bash
set -e

# Read options from HA config
DB_HOST=$(bashio::config 'db_host')
DB_NAME=$(bashio::config 'db_name')
DB_USER=$(bashio::config 'db_user')
DB_PASS=$(bashio::config 'db_pass')
WP_DOMAIN=$(bashio::config 'wp_domain')
WP_TITLE=$(bashio::config 'wp_site_title')
WP_ADMIN=$(bashio::config 'wp_admin_user')
WP_PASS=$(bashio::config 'wp_admin_password')
WP_EMAIL=$(bashio::config 'wp_admin_email')

WP_PATH="/var/www/html"

bashio::log.info "Starting WWW WordPress Suite..."

# Start Apache
httpd -D FOREGROUND &
APACHE_PID=$!

# Wait for DB
bashio::log.info "Waiting for database at ${DB_HOST}..."
until mysqladmin ping -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" --silent 2>/dev/null; do
    sleep 3
done

# Download WordPress core if not present
if [ ! -f "${WP_PATH}/wp-login.php" ]; then
    bashio::log.info "Downloading WordPress core..."
    wp core download --path="${WP_PATH}" --allow-root --quiet

    bashio::log.info "Configuring WordPress..."
    wp config create \
        --path="${WP_PATH}" \
        --dbname="${DB_NAME}" \
        --dbuser="${DB_USER}" \
        --dbpass="${DB_PASS}" \
        --dbhost="${DB_HOST}" \
        --allow-root --quiet

    bashio::log.info "Installing WordPress..."
    wp db create --path="${WP_PATH}" --allow-root --quiet 2>/dev/null || true
    wp core install \
        --path="${WP_PATH}" \
        --url="http://${WP_DOMAIN}:8082" \
        --title="${WP_TITLE}" \
        --admin_user="${WP_ADMIN}" \
        --admin_password="${WP_PASS}" \
        --admin_email="${WP_EMAIL}" \
        --skip-email \
        --allow-root --quiet

    # Copy all preloaded plugins — do NOT activate any
    bashio::log.info "Installing plugins (not activating)..."
    cp -rn /var/www/html/wp-content/plugins-preloaded/. "${WP_PATH}/wp-content/plugins/"

    bashio::log.info "WordPress Suite ready. Plugins installed but not activated — activate from WP Admin."
fi

wait ${APACHE_PID}
