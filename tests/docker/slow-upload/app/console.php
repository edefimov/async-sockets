<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Console\Application;

$rootDir = getenv('ASYNC_SOCKETS_ROOT');
if (!$rootDir) {
    $rootDir = __DIR__ . '/../../../..';
}
require_once $rootDir . '/vendor/autoload.php';
require_once __DIR__ . '/SlowUploadTestCommand.php';

$application = new Application();
$command     = new SlowUploadTestCommand();
$application->add($command);
$application->setDefaultCommand($command->getName());

$application->run();
