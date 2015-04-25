<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo;

use AsyncSockets\Event\Event;
use Demo\SystemNotificationSample\Client;
use Demo\SystemNotificationSample\Logger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class SystemNotificationSample
 */
class SystemNotificationSample
{
    /**
     * Main
     *
     * @return int
     */
    public function main()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher', true)) {
            echo <<<HELP
To run this demo you should have symfony/event-dispatcher package installed.
You can add it by running command:

  \$ composer require \"symfony/event-dispatcher\" \"*\"

You are free to choose any version, greater or equals to 2.0.0
HELP;
            return -1;
        }

        $logger     = new Logger();
        $dispatcher = new EventDispatcher();
        $client     = new Client($dispatcher);

        $ref = new \ReflectionClass('AsyncSockets\Event\EventType');
        foreach ($ref->getConstants() as $eventType) {
            $dispatcher->addListener($eventType, $this->getEventHandler($logger));
        }

        $client->process();

        return 0;
    }

    /**
     * Return simple event handler
     *
     * @param Logger $logger Logger
     *
     * @return callable
     */
    private function getEventHandler(Logger $logger)
    {
        return function (Event $event) use ($logger) {
            $logger->log(
                'Notified about event: ' . $event->getType() . ' on socket ' . md5(spl_object_hash($event->getSocket()))
            );
        };
    }
}
