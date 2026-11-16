FROM php:8.4.21-apache-bookworm

# 1. system packages + Node.js 24 (single layer, apt lists removed afterwards)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg git zip unzip sudo g++ \
        libicu-dev libbz2-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libreadline-dev libgmp-dev libzip-dev libonig-dev \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_24.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/*

# 2. PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bz2 intl iconv bcmath opcache calendar mbstring pdo_mysql zip gd gmp

# 3. development php.ini
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# 4. apache: document root, mod_rewrite for URL rewrite and mod_headers for .htaccess extra headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

# 5. composer (pinned for reproducible builds; bump deliberately)
COPY --from=composer:2.9.7 /usr/bin/composer /usr/bin/composer

# 6. we need a user with the same UID/GID with host user
# so when we execute CLI commands, all the host file's ownership remains intact
# otherwise command from inside container will create root-owned files and directories
ARG uid
RUN useradd -G www-data,root -u $uid -d /home/devuser devuser \
    && mkdir -p /home/devuser/.composer \
    && chown -R devuser:devuser /home/devuser
