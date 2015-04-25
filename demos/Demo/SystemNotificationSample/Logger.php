<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\SystemNotificationSample;
 
/**
 * Class Logger
 */
class Logger
{
    /**
     * Simple log
     *
     * @param string $message Message
     *
     * @return void
     */
    public function log($message)
    {
        $now = new \DateTime();
        echo '[' . $now->format('Y-m-d H:i:s') . ']: ' . $message . "\n";
    }
}
