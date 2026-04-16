#!/usr/bin/env bash
set -e

OPTIONS_FILE="/data/options.json"

json_get() {
    jq -r "$1" "$OPTIONS_FILE"
}

# Read options from HA config
USE_INTERNAL_DB=$(json_get '.use_internal_db')
DB_HOST=$(json_get '.db_host')
DB_NAME=$(json_get '.db_name')
DB_USER=$(json_get '.db_user')
DB_PASS=$(json_get '.db_pass')
WP_DOMAIN=$(json_get '.wp_domain')
WP_TITLE=$(json_get '.wp_site_title')
WP_ADMIN=$(json_get '.wp_admin_user')
WP_PASS=$(json_get '.wp_admin_password')
WP_EMAIL=$(json_get '.wp_admin_email')

WP_PATH="/var/www/html"

echo "Starting WWW WordPress Suite..."

# Handle Internal MariaDB
if [ "$USE_INTERNAL_DB" == "true" ]; then
    DB_HOST="127.0.0.1"
    MYSQL_DATA_DIR="/data/mysql"

    if [ ! -d "$MYSQL_DATA_DIR/mysql" ]; then
        echo "Initializing MariaDB in $MYSQL_DATA_DIR..."
        mysql_install_db --user=mysql --datadir="$MYSQL_DATA_DIR" > /dev/null
    fi

    echo "Starting internal MariaDB server..."
    mariadbd --user=mysql --datadir="$MYSQL_DATA_DIR" --bind-address=127.0.0.1 &
    MARIADB_PID=$!

    echo "Waiting for internal MariaDB to be ready..."
    until mysqladmin ping --silent; do
        sleep 2
    done

    # Provision DB and User
    echo "Provisioning internal database '$DB_NAME' and user '$DB_USER'..."
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
    mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
fi

# Start Apache
echo "Starting Apache server..."
httpd -D FOREGROUND &
APACHE_PID=$!

# Wait for DB (If external, wait for it; if internal, we already waited)
if [ "$USE_INTERNAL_DB" != "true" ]; then
    echo "Waiting for external database at ${DB_HOST}..."
    until mysqladmin ping -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" --silent 2>/dev/null; do
        sleep 3
    done
fi

# Download WordPress core if not present
if [ ! -f "${WP_PATH}/wp-login.php" ]; then
    echo "Downloading WordPress core..."
    wp core download --path="${WP_PATH}" --allow-root --quiet

    echo "Configuring WordPress..."
    wp config create \
        --path="${WP_PATH}" \
        --dbname="${DB_NAME}" \
        --dbuser="${DB_USER}" \
        --dbpass="${DB_PASS}" \
        --dbhost="${DB_HOST}" \
        --allow-root --quiet

    echo "Installing WordPress..."
    wp core install \
        --path="${WP_PATH}" \
        --url="http://${WP_DOMAIN}:8082" \
        --title="${WP_TITLE}" \
        --admin_user="${WP_ADMIN}" \
        --admin_password="${WP_PASS}" \
        --admin_email="${WP_EMAIL}" \
        --skip-email \
        --allow-root --quiet

    # Copy all preloaded plugins
    echo "Installing pre-loaded plugins..."
    if [ -d "/var/www/html/wp-content/plugins-preloaded" ]; then
        cp -rn /var/www/html/wp-content/plugins-preloaded/. "${WP_PATH}/wp-content/plugins/"
    fi

    echo "WordPress Suite ready. Plugins installed — activate from WP Admin."
fi

# Monitor processes
echo "Processes running. Monitoring..."
wait -n ${MARIADB_PID} ${APACHE_PID}

