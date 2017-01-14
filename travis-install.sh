#!/usr/bin/env bash
set -e

if [[ "${TRAVIS_PHP_VERSION}" = "5.4" || "${TRAVIS_PHP_VERSION}" = "5.5" ]]; then
    PHP_UNIT_VERSION=~4.7
else
    PHP_UNIT_VERSION=~5.2
fi

if [[ "${TRAVIS_PHP_VERSION}" != "hhvm" &&
      "${TRAVIS_PHP_VERSION}" != "7.0" &&
      "${TRAVIS_PHP_VERSION}" != "7.1" &&
      "${TRAVIS_PHP_VERSION}" != "nightly" ]]; then
    echo "yes" | pecl install event

    # install 'libevent' PHP extension
    curl http://pecl.php.net/get/libevent-0.1.0.tgz | tar -xz
    pushd libevent-0.1.0
        phpize
        ./configure
        make
        make install
    popd

    echo "extension=libevent.so" >> "$(php -r 'echo php_ini_loaded_file();')"
fi

composer install --prefer-dist
composer require phpunit/phpunit:${PHP_UNIT_VERSION}
php tests/console.php async_sockets:test:warmup --configuration=${ASYNC_SOCKETS_CONFIG}

