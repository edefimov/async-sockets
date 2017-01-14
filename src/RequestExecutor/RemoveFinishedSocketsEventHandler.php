<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;

/**
 * Class RemoveFinishedSocketsEventHandler
 */
class RemoveFinishedSocketsEventHandler implements EventHandlerInterface
{
    /**
     * Target handler
     *
     * @var EventHandlerInterface
     */
    private $handler;

    /**
     * RemoveFinishedSocketsEventHandler constructor.
     *
     * @param EventHandlerInterface $handler Original event handler
     */
    public function __construct(EventHandlerInterface $handler = null)
    {
        $this->handler = $handler;
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        if ($this->handler) {
            $this->handler->invokeEvent($event);
        }

        if ($event->getType() === EventType::FINALIZE) {
            $bag    = $event->getExecutor()->socketBag();
            $socket = $event->getSocket();
            if ($bag->hasSocket($socket)) {
                $bag->removeSocket($socket);
            }
        }
    }
}
