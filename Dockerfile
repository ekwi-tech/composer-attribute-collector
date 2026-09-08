# Development image: everything the Makefile needs to install, analyse and test the plugin.
#
#     docker build --build-arg PHP_VERSION=8.4 --tag composer-attribute-collector-dev .
#
# It is not used to run the plugin — that happens inside the user's own composer.
ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git and unzip let composer install from source and from dist; xdebug drives `make test-coverage`
# and is left off by default, the XDEBUG_MODE environment variable turns it on for a single run.
RUN apk add --no-cache git unzip \
    && apk add --no-cache --virtual .build-dependencies $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-dependencies \
    && printf 'xdebug.mode=off\n' > "$PHP_INI_DIR/conf.d/zz-xdebug-mode.ini"

ENV COMPOSER_ALLOW_SUPERUSER=1

# phpcs is not a dev dependency of the plugin, it is baked into the image. It goes to /opt/phpcs
# rather than the default COMPOSER_HOME, which the Makefile shadows with the host's cache volume.
RUN COMPOSER_HOME=/opt/phpcs composer global require --no-interaction --no-progress \
        squizlabs/php_codesniffer \
    && ln -s /opt/phpcs/vendor/bin/phpcs /usr/local/bin/phpcs \
    && chmod -R a+rX /opt/phpcs

WORKDIR /src
