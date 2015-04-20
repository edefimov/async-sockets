<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require_once __DIR__ . '/../vendor/autoload.php';

register_shutdown_function(function () {
    echo "\n\nStatistics:\n";
    echo ' - Memory usage: ' . number_format(memory_get_usage(true), 0, '.', ' ') . " bytes\n";
    echo ' - Memory peak usage: ' . number_format(memory_get_peak_usage(true), 0, '.', ' ') . " bytes\n";
});
