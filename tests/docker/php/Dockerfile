# Archon test / reference runtime image.
#
# The same Dockerfile builds every PHP version in the test matrix:
#
#   PHP_VERSION=8.2  SQLSRV_VERSION=5.12.0   (target runtime, 8.2.35+ includes the php2shell fix)
#   PHP_VERSION=7.4  SQLSRV_VERSION=5.10.1   (fallback runtime -- see tests/README.md about php2shell)
#   PHP_VERSION=5.6  INSTALL_SQLSRV=0        (legacy baseline used for differential testing only)
#
# Build context: the tests/ directory. Third-party sources are pinned and verified with SHA-256 checksums.
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

ARG PHP_VERSION
ARG INSTALL_SQLSRV=1
ARG SQLSRV_VERSION=5.12.0
ARG SQLSRV_SHA256=c2a67f20a49555243a1b6c45d4a735e58dcbed0bafefbf7de8e196f317544fbc
# PEAR MDB2 is installed from the archived upstream repository, which also
# contains the post-2.5.0b5 PHP 7 fixes that never made it to a PEAR release.
ARG MDB2_COMMIT=e1c5a7887a1dca880367430d149f65a539120428
ARG MDB2_SHA256=48b05855f68a69c22e6e98a432f67e40a7d7c1f4978c9697d39dac05c5428ea5

ENV DEBIAN_FRONTEND=noninteractive \
    ACCEPT_EULA=Y

# PHP 5.6 images are based on Debian 9 whose packages only live on archive.debian.org now.
RUN set -eux; \
    . /etc/os-release; \
    if [ "${VERSION_CODENAME:-}" = "stretch" ]; then \
        printf 'deb http://archive.debian.org/debian stretch main\ndeb http://archive.debian.org/debian-security stretch/updates main\n' > /etc/apt/sources.list; \
        printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive; \
    fi; \
    apt-get update; \
    apt-get install -y --no-install-recommends --allow-unauthenticated \
        ca-certificates curl git gnupg rsync unzip \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev zlib1g-dev; \
    case "${PHP_VERSION}" in \
        5.*) docker-php-ext-configure gd --with-freetype-dir=/usr --with-jpeg-dir=/usr --with-png-dir=/usr; \
             docker-php-ext-install -j"$(nproc)" mysql mysqli gd zip ;; \
        *)   docker-php-ext-configure gd --with-freetype --with-jpeg; \
             docker-php-ext-install -j"$(nproc)" mysqli gd zip ;; \
    esac; \
    rm -rf /var/lib/apt/lists/*

# Microsoft ODBC Driver 18 + sqlsrv extension (built from the pinned upstream release tag).
RUN set -eux; \
    if [ "${INSTALL_SQLSRV}" = "1" ]; then \
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
        rm -rf /var/lib/apt/lists/*; \
    fi

# PEAR MDB2 (+ all drivers) into the default PEAR include directory.
RUN set -eux; \
    curl -fsSL -o /tmp/mdb2.tar.gz "https://codeload.github.com/pear/MDB2/tar.gz/${MDB2_COMMIT}"; \
    echo "${MDB2_SHA256}  /tmp/mdb2.tar.gz" | sha256sum -c -; \
    mkdir -p /tmp/mdb2; \
    tar xzf /tmp/mdb2.tar.gz -C /tmp/mdb2 --strip-components=1; \
    PEAR_DIR="$(php -r 'foreach (explode(PATH_SEPARATOR, get_include_path()) as $p) { if ($p !== "." && is_file($p . "/PEAR.php")) { echo $p; break; } }')"; \
    test -n "${PEAR_DIR}"; \
    cp -r /tmp/mdb2/MDB2.php /tmp/mdb2/MDB2 "${PEAR_DIR}/"; \
    rm -rf /tmp/mdb2 /tmp/mdb2.tar.gz

# PHPUnit for the test suite (PHP 7.3+ only; the 5.6 baseline is exercised over HTTP only).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock /opt/archon-tests/
RUN set -eux; \
    case "${PHP_VERSION}" in \
        5.*) rm -f /opt/archon-tests/composer.* ;; \
        *)   cd /opt/archon-tests; \
             export COMPOSER_ALLOW_SUPERUSER=1; \
             composer install --no-interaction --no-progress --prefer-dist \
               || composer install --no-interaction --no-progress --prefer-source ;; \
    esac

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-archon-test.ini
COPY docker/php/archon-sync /usr/local/bin/archon-sync
COPY docker/php/error-capture.php /usr/local/lib/archon-test/error-capture.php
RUN set -eux; \
    chmod +x /usr/local/bin/archon-sync; \
    mkdir -p /var/log/archon; \
    chown www-data:www-data /var/log/archon; \
    a2enmod rewrite >/dev/null

WORKDIR /var/www/html
