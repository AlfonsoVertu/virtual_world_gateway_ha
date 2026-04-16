ARG BUILD_FROM
FROM $BUILD_FROM

# Install Apache, PHP, MariaDB client, WP-CLI
RUN apk add --no-cache \
    apache2 \
    php83 \
    php83-apache2 \
    php83-mysqli \
    php83-curl \
    php83-json \
    php83-mbstring \
    php83-gd \
    php83-xml \
    php83-zip \
    php83-opcache \
    php83-intl \
    php83-phar \
    php83-openssl \
    php83-fileinfo \
    php83-exif \
    php83-tokenizer \
    php83-dom \
    mariadb \
    mariadb-server-utils \
    mariadb-client \
    php83-mysqli \
    php83-pdo_mysql \
    wget \
    curl \
    bash \
    dos2unix

# Install WP-CLI
RUN wget -q https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -O /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

# Copy plugins into the image
COPY plugins/ /var/www/html/wp-content/plugins-preloaded/

# Download Shortcoder from WordPress.org
RUN wget -q https://downloads.wordpress.org/plugin/shortcoder.latest-stable.zip -O /tmp/shortcoder.zip \
    && unzip -q /tmp/shortcoder.zip -d /var/www/html/wp-content/plugins-preloaded/ \
    && rm /tmp/shortcoder.zip

COPY run.sh /run.sh
RUN dos2unix /run.sh && chmod +x /run.sh

CMD ["/run.sh"]
