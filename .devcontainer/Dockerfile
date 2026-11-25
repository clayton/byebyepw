ARG PHP_VERSION=8.6
FROM php:${PHP_VERSION}-apache

ARG PHP_VERSION=8.6
ENV DEBIAN_FRONTEND=noninteractive
ENV WP_CLI_ALLOW_ROOT=1

# Install system dependencies (with archive fallback for older Debian bases like php:8.0-apache)
RUN set -eux; \
    if ! apt-get update; then \
        if [ -f /etc/apt/sources.list ]; then \
            sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g; s|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list; \
        fi; \
        apt-get update -o Acquire::Check-Valid-Until=false; \
    fi; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        git \
        jq \
        less \
        libfreetype6-dev \
        libicu-dev \
        libjpeg-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        openssl \
        unzip \
        zip; \
    rm -rf /var/lib/apt/lists/*

# Install WordPress + WebAuthn PHP extensions cleanly across PHP 8.0 / 8.2 / 8.4 / 8.6
RUN set -eux; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mysqli \
        pdo_mysql \
        zip; \
    # PHP 8.5+ compiles OPcache statically into core; only install when not already present
    if ! php -m | grep -qi '^Zend OPcache$'; then \
        docker-php-ext-install -j"$(nproc)" opcache; \
    fi

# Enable required Apache modules & configure HTTPS on :443 and :8443
RUN set -eux; \
    a2enmod ssl rewrite headers; \
    grep -q '^Listen 8443$' /etc/apache2/ports.conf || echo 'Listen 8443' >> /etc/apache2/ports.conf

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY php/conf.d/99-byebyepw.ini /usr/local/etc/php/conf.d/99-byebyepw.ini

# Install WP-CLI
RUN set -eux; \
    curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp; \
    chmod +x /usr/local/bin/wp; \
    mkdir -p /root/.wp-cli /var/www/.wp-cli; \
    printf "path: /var/www/html\nallow-root: true\n" > /root/.wp-cli/config.yml; \
    printf "path: /var/www/html\n" > /var/www/.wp-cli/config.yml; \
    chown -R www-data:www-data /var/www/.wp-cli

# Install helper scripts inside the container
COPY scripts/generate-certs.sh /usr/local/bin/generate-certs.sh
COPY scripts/switch-wp.sh /usr/local/bin/switch-wp.sh
COPY scripts/bootstrap-wp.sh /usr/local/bin/bootstrap-wp.sh
COPY scripts/docker-entrypoint.sh /usr/local/bin/byebyepw-entrypoint.sh
RUN chmod +x \
    /usr/local/bin/generate-certs.sh \
    /usr/local/bin/switch-wp.sh \
    /usr/local/bin/bootstrap-wp.sh \
    /usr/local/bin/byebyepw-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80 443 8443

ENTRYPOINT ["/usr/local/bin/byebyepw-entrypoint.sh"]
CMD ["apache2-foreground"]
