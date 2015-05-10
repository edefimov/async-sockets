<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\Socket\SocketInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class EventDispatcherAwareRequestExecutor
 */
class EventDispatcherAwareRequestExecutor extends RequestExecutor implements EventDispatcherAware
{
    /**
     * EventDispatcherInterface
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /** {@inheritdoc} */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /** {@inheritdoc} */
    protected function handleSocketEvent(
        SocketInterface $socket,
        Event $event
    ) {
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch($event->getType(), $event);
        }
    }
}
