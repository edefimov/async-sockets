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

require_once 'autoload.php';

$index = null;
foreach ($_SERVER['argv'] as $key => $argValue) {
    if ($argValue === '--') {
        $index = $key + 1;
        break;
    }
}

if ($index === null || $index >= count($_SERVER['argv'])) {
    $name = basename(__DIR__) . DIRECTORY_SEPARATOR . basename(__FILE__);
    echo <<<"HELP"
You should specify demo name after -- string
Example:
   php {$name} -- SimpleClient
HELP;
    return -1;
}

$demoClass = $_SERVER['argv'][$index];
$className = "Demo\\{$demoClass}";
$classFile = __DIR__ . DIRECTORY_SEPARATOR . 'Demo' . DIRECTORY_SEPARATOR . $demoClass . '.php';

if (!file_exists($classFile)) {
    echo "Demo {$demoClass} does not exist\n";
    return -1;
}

require_once $classFile;

$class = new $className;
$code = call_user_func_array([$class, 'main'], []) ?: 0;
return $code;
