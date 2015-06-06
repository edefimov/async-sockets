#!/usr/bin/env php
<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Console\Application;

require_once __DIR__ . '/autoload.php';

$application = new Application();

foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . 'Demo' . DIRECTORY_SEPARATOR . '*.php') as $filename) {
    $class = __NAMESPACE__ . 'Demo\\' . substr(basename($filename), 0, -4);
    $application->add(new $class);
}

$application->run();
