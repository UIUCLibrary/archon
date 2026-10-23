# Archon test runtime: PHP 8.2 + Microsoft sqlsrv driver (SQL Server), as an Apache module or PHP-FPM.
#
# Build context: the repository root (see /.dockerignore). Third-party sources are pinned and verified
# with SHA-256 checksums. PHP 8.2.35+ includes the fix for the php2shell vulnerability; the base image
# tag tracks the latest 8.2 patch.
#
# PHP_VARIANT selects the server API:
#   apache (default) - tests/docker/docker-compose.yml, the developer/agent harness (tests/bin/archon-test).
#                      It mounts the working tree at /src and bypasses the entrypoint.
#   fpm              - tests/testenv/compose.yaml, the one-command test environment. PHP-FPM listens on
#                      port 9000 behind the host's Apache httpd (tests/testenv/httpd/archon-testenv.conf).
#                      The entrypoint (archon-entrypoint) creates one Archon site per database from the
#                      source baked into /opt/archon/src.
ARG PHP_VERSION=8.2
ARG PHP_VARIANT=apache
FROM docker.io/library/php:${PHP_VERSION}-${PHP_VARIANT}

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
COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/local/bin/composer
COPY tests/composer.json tests/composer.lock /opt/archon-tests/
RUN set -eux; \
    cd /opt/archon-tests; \
    export COMPOSER_ALLOW_SUPERUSER=1; \
    composer install --no-interaction --no-progress --prefer-dist \
      || { rm -rf vendor; composer install --no-interaction --no-progress --prefer-source; }; \
    find vendor -name .git -type d -prune -exec rm -rf {} +; \
    composer clear-cache; \
    rm -rf /root/.composer/cache /root/.cache/composer

COPY tests/docker/php/php.ini /usr/local/etc/php/conf.d/zz-archon-test.ini
COPY tests/docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-archon.conf
COPY tests/docker/php/archon-sync tests/docker/php/archon-entrypoint tests/docker/php/archon-testenv /usr/local/bin/
COPY tests/docker/php/error-capture.php /usr/local/lib/archon-test/error-capture.php
COPY tests/docker/php/archon-static.php /usr/local/lib/archon/static.php
COPY tests/docker/php/apache-archon.conf /usr/local/lib/archon/apache-archon.conf
RUN set -eux; \
    chmod +x /usr/local/bin/archon-sync /usr/local/bin/archon-entrypoint /usr/local/bin/archon-testenv; \
    mkdir -p /var/log/archon; \
    chown www-data:www-data /var/log/archon; \
    if [ -d /etc/apache2 ]; then \
        a2enmod rewrite remoteip >/dev/null; \
        echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf; \
        a2enconf servername >/dev/null; \
        mv /usr/local/lib/archon/apache-archon.conf /etc/apache2/conf-available/archon.conf; \
        a2enconf archon >/dev/null; \
        a2dissite 000-default >/dev/null; \
    else \
        rm /usr/local/lib/archon/apache-archon.conf; \
    fi

# The Archon source used by the test environment (the developer harness mounts /src instead).
COPY . /opt/archon/src
ENV ARCHON_SOURCE_DIR=/opt/archon/src

WORKDIR /var/www/html
ENTRYPOINT ["archon-entrypoint"]
# "serve": initialise the sites, then run Apache (apache variant) or PHP-FPM (fpm variant).
CMD ["serve"]
