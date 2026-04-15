FROM wordpress:6.6.2-php8.2-apache

# Install dependencies for HA environment and WP-CLI
RUN apt-get update && apt-get install -y \
    jq \
    curl \
    mariadb-client \
    unzip \
    dos2unix \
    && rm -rf /var/lib/apt/lists/*

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Copy Custom Plugin
COPY plugin/virtual-world-gate-key /var/www/html/wp-content/plugins/virtual-world-gate-key/

# Copy entrypoint script
COPY run.sh /run.sh
RUN dos2unix /run.sh && chmod +x /run.sh

ENTRYPOINT ["/run.sh"]
