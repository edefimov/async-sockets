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

use Tests\AsyncSockets\Application\Configuration;

$configuration = new Configuration(
    __DIR__ . '/..',
    getenv('ASYNC_SOCKETS_CONFIG') ?: 'config.yaml'
);

include $configuration->cacheDir() . '/phpmocker/autoload.php';
