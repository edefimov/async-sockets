<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Console\Application;

$rootDir = getenv('ASYNC_SOCKETS_ROOT');
require_once $rootDir . '/vendor/autoload.php';
require_once __DIR__ . '/UdpDelayTestCommand.php';

$application = new Application();
$application->add(new UdpDelayTestCommand());
$application->setDefaultCommand('test:udp-delay');

$application->run();
