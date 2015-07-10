<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require_once __DIR__ . '/autoload.php';

use Tests\Application\Command\Configuration;

$configuration = new Configuration(
    __DIR__ . '/..',
    getenv('ASYNC_SOCKETS_CONFIG') ?: 'config.yml'
);

$cacheFile = $configuration->cacheDir() . '/phpmocker/autoload.php';
if (!is_file($cacheFile)) {
    throw new RuntimeException(
        <<<MESSAGE
Can not find php mocker cache files. Did you forget to run

php tests/console.php async_sockets:test:warmup

?

MESSAGE
    );
}

include_once $cacheFile;
