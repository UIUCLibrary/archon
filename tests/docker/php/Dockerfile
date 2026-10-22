# Archon test runtime: PHP 8.2 + Apache + Microsoft sqlsrv driver (SQL Server).
#
# Build context: the tests/ directory. Third-party sources are pinned and verified with SHA-256 checksums.
# PHP 8.2.35+ includes the fix for the php2shell vulnerability; the base image tag tracks the latest 8.2 patch.
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

ARG SQLSRV_VERSION=5.12.0
ARG SQLSRV_SHA256=c2a67f20a49555243a1b6c45d4a735e58dcbed0bafefbf7de8e196f317544fbc

ENV DEBIAN_FRONTEND=noninteractive \
    ACCEPT_EULA=Y

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates curl git gnupg rsync unzip \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev zlib1g-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd zip; \
    rm -rf /var/lib/apt/lists/*

# Microsoft ODBC Driver 18 + sqlsrv extension (built from the pinned upstream release tag;
# pecl.php.net is not required).
RUN set -eux; \
    . /etc/os-release; \
    curl -fsSL -o /tmp/packages-microsoft-prod.deb "https://packages.microsoft.com/config/debian/${VERSION_ID}/packages-microsoft-prod.deb"; \
    dpkg -i /tmp/packages-microsoft-prod.deb; \
    rm -f /tmp/packages-microsoft-prod.deb; \
    apt-get update; \
    apt-get install -y --no-install-recommends msodbcsql18 unixodbc-dev ${PHPIZE_DEPS}; \
    curl -fsSL -o /tmp/msphpsql.tar.gz "https://codeload.github.com/microsoft/msphpsql/tar.gz/refs/tags/v${SQLSRV_VERSION}"; \
    echo "${SQLSRV_SHA256}  /tmp/msphpsql.tar.gz" | sha256sum -c -; \
    mkdir -p /tmp/msphpsql; \
    tar xzf /tmp/msphpsql.tar.gz -C /tmp/msphpsql --strip-components=1; \
    cp -r /tmp/msphpsql/source/shared /tmp/msphpsql/source/sqlsrv/; \
    cd /tmp/msphpsql/source/sqlsrv; \
    phpize; \
    ./configure CXXFLAGS=-std=c++11; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable sqlsrv; \
    cd /; rm -rf /tmp/msphpsql /tmp/msphpsql.tar.gz; \
    apt-get purge -y --auto-remove ${PHPIZE_DEPS}; \
    rm -rf /var/lib/apt/lists/*

# PHPUnit for the test suite.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock /opt/archon-tests/
RUN set -eux; \
    cd /opt/archon-tests; \
    export COMPOSER_ALLOW_SUPERUSER=1; \
    composer install --no-interaction --no-progress --prefer-dist \
      || composer install --no-interaction --no-progress --prefer-source

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-archon-test.ini
COPY docker/php/archon-sync /usr/local/bin/archon-sync
COPY docker/php/error-capture.php /usr/local/lib/archon-test/error-capture.php
RUN set -eux; \
    chmod +x /usr/local/bin/archon-sync; \
    mkdir -p /var/log/archon; \
    chown www-data:www-data /var/log/archon; \
    a2enmod rewrite >/dev/null

WORKDIR /var/www/html
