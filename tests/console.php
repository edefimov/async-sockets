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
use Tests\AsyncSockets\Application\WarmupCommand;

require_once __DIR__ . '/autoload.php';

$application = new Application();
$application->addCommands(
    [
        new WarmupCommand()
    ]
);

$application->run();
