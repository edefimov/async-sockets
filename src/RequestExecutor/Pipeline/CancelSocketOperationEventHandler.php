<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Exception\StopSocketOperationException;
use AsyncSockets\RequestExecutor\EventHandlerInterface;

/**
 * Class CancelSocketOperationEventHandler
 */
class CancelSocketOperationEventHandler implements EventHandlerInterface
{
    /**
     * Decorated event handler
     *
     * @var EventHandlerInterface
     */
    private $original;

    /**
     * CancelSocketOperationEventHandler constructor.
     *
     * @param EventHandlerInterface $original Decorated event handler
     */
    public function __construct(EventHandlerInterface $original)
    {
        $this->original = $original;
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        if ($this->isStopOperationExceptionEvent($event)) {
            return;
        }

        $this->original->invokeEvent($event);

        if ($event->isOperationCancelled()) {
            throw new StopSocketOperationException();
        }
    }

    /**
     * Checks whether given event is StopSocketOperationException and if so ignore processing it by listeners
     *
     * @param Event $event Test event object
     *
     * @return bool
     */
    private function isStopOperationExceptionEvent(Event $event)
    {
        if (!($event instanceof SocketExceptionEvent)) {
            return false;
        }

        $exception = $event->getException();
        return $exception instanceof StopSocketOperationException;
    }
}
