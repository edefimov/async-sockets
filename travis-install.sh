#!/usr/bin/env bash
set -e

rm ./composer.lock

if [[ "${TRAVIS_PHP_VERSION}" = "5.4" || "${TRAVIS_PHP_VERSION}" = "5.5" ]]; then
    PHP_UNIT_VERSION=~4.7
else
    PHP_UNIT_VERSION=~5.2
fi

composer install --prefer-dist
composer require phpunit/phpunit:${PHP_UNIT_VERSION}
php tests/console.php async_sockets:test:warmup --configuration=${ASYNC_SOCKETS_CONFIG}

