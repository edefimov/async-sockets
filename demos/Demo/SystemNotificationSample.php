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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class SystemNotificationSample
 */
class SystemNotificationSample extends Command
{
    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this->setName('demo:system_notification_sample')
            ->setDescription('Demonstrates interaction with external parts of system');
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher', true)) {
            $output->writeln(<<<HELP
<error>To run this demo you should have symfony/event-dispatcher package installed.
You can add it by running command:

  \$ composer require \"symfony/event-dispatcher\" \"*\"

You are free to choose any version</error>
HELP
            );
        }

        $logger     = new Logger($output);
        $dispatcher = new EventDispatcher();
        $client     = new Client($dispatcher, $output);

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
