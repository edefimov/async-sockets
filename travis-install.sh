#!/usr/bin/env bash
set -e

if [[ "$TRAVIS_PHP_VERSION" != "hhvm" &&
      "$TRAVIS_PHP_VERSION" != "7.0" &&
      "$TRAVIS_PHP_VERSION" != "nightly" ]]; then
    sudo apt-get install -y libevent-dev

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

composer install --prefer-dist --dev
composer require phpunit/phpunit:4.7.*
alias phpunit=vendor/bin/phpunit
php tests/console.php async_sockets:test:warmup --configuration=$ASYNC_SOCKETS_CONFIG
