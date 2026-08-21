FROM ubuntu:24.04

LABEL maintainer="Jefferson Oliveira"

ARG WWWGROUP
ARG NODE_VERSION=24
ARG MYSQL_CLIENT="mysql-client"
ARG PHP_EXTENSIONS=""

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV LANG=C.UTF-8
ENV SUPERVISOR_PHP_USER="sage"
ENV WEB_ROOT="/var/www/html/web"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor nginx gettext-base sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.5-cli \
        php8.5-fpm \
        php8.5-dev \
        php8.5-sqlite3 \
        php8.5-gd \
        php8.5-curl \
        php8.5-mysql \
        php8.5-mbstring \
        php8.5-xml \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-soap \
        php8.5-intl \
        php8.5-readline \
        php8.5-msgpack \
        php8.5-igbinary \
        php8.5-redis \
        php8.5-memcached \
        php8.5-pcov \
        php8.5-imagick \
        php8.5-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && COMPOSER_HOME=/usr/local/share/composer composer global require cpx/cpx:^2.0 \
    && ln -s /usr/local/share/composer/vendor/bin/cpx /usr/local/bin/cpx \
    && curl -sSLo /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm pnpm bun corepack \
    && corepack enable \
    && corepack prepare yarn@stable --activate \
    && npx -y playwright install-deps \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN if [ -n "$PHP_EXTENSIONS" ]; then \
    apt-get update \
    && apt-get install -y $(for ext in $PHP_EXTENSIONS; do echo "php8.5-$ext"; done) \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*; \
fi

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sage
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sage
RUN git config --global --add safe.directory /var/www/html

# Nginx workers read the bind mount as the application user...
RUN sed -i 's/^user www-data;/user sage;/' /etc/nginx/nginx.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /run/php

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY nginx.conf.template /etc/nginx/templates/default.conf.template
COPY php-fpm-pool.conf /etc/php/8.5/fpm/pool.d/www.conf
COPY php.ini /etc/php/8.5/cli/conf.d/99-sage-sail.ini
COPY php.ini /etc/php/8.5/fpm/conf.d/99-sage-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp
EXPOSE 5173/tcp

ENTRYPOINT ["start-container"]
