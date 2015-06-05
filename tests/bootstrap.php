<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Composer\Autoload\ClassLoader;

/** @var ClassLoader $classLoader */
$classLoader = require_once __DIR__ . '/../vendor/autoload.php';
$classLoader->addPsr4('Tests\\AsyncSockets\\', __DIR__);

Tests\AsyncSockets\Mock\PhpFunctionMocker::bootstrap($classLoader);
