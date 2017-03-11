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

use AsyncSockets\Event\DataAlertEvent;
use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Frame\EmptyFrame;
use AsyncSockets\Frame\EmptyFramePicker;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SslDataFlushEventHandler. This decorator flushes unread data containing in socket buffer
 * which will be evaluated to empty string
 */
class SslDataFlushEventHandler implements EventHandlerInterface
{
    /**
     * Next handler
     *
     * @var EventHandlerInterface|null
     */
    private $next;

    /**
     * Array of socket descriptors currently in flush
     *
     * @var bool[]
     */
    private $inFlushingOperations = [];

    /**
     * SslDataFlushEventHandler constructor.
     *
     * @param EventHandlerInterface|null $next Next event handler
     */
    public function __construct(EventHandlerInterface $next = null)
    {
        $this->next = $next;
    }

    /** {@inheritdoc} */
    public function invokeEvent(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        switch ($event->getType()) {
            case EventType::DATA_ALERT:
                /** @var DataAlertEvent $event */
                $this->onDataAlert($event, $executor, $socket, $context);
                break;
            case EventType::READ:
                /** @var ReadEvent $event */
                $this->onRead($event, $executor, $socket, $context);
                break;
            default:
                $this->callNextHandler($event, $executor, $socket, $context);
        }
    }

    /**
     * Handle first data alert and try to flush ssl data by empty frame
     *
     * @param DataAlertEvent           $event    Event object
     * @param RequestExecutorInterface $executor Request executor fired an event
     * @param SocketInterface          $socket   Socket connected with event
     * @param ExecutionContext         $context  Global data context
     *
     * @return void
     */
    private function onDataAlert(
        DataAlertEvent $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        if ($event->getAttempt() !== 1) {
            $this->callNextHandler($event, $executor, $socket, $context);
            return;
        }

        $event->nextIs(
            new ReadOperation(
                new EmptyFramePicker()
            )
        );

        $key = spl_object_hash($event->getSocket());
        $this->inFlushingOperations[$key] = true;
    }

    /**
     * Handle read data
     *
     * @param ReadEvent                $event    Read event
     * @param RequestExecutorInterface $executor Request executor fired an event
     * @param SocketInterface          $socket   Socket connected with event
     * @param ExecutionContext         $context  Global data context
     *
     * @return void
     */
    private function onRead(
        ReadEvent $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        $key   = spl_object_hash($event->getSocket());
        $frame = $event->getFrame();
        if (!($frame instanceof EmptyFrame) || !isset($this->inFlushingOperations[$key])) {
            $this->callNextHandler($event, $executor, $socket, $context);
            return;
        }

        unset($this->inFlushingOperations[$key]);
    }

    /**
     * Call next event handler
     *
     * @param Event                    $event    Event object
     * @param RequestExecutorInterface $executor Request executor fired an event
     * @param SocketInterface          $socket   Socket connected with event
     * @param ExecutionContext         $context  Global data context
     *
     * @return void
     */
    private function callNextHandler(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        if ($this->next) {
            $this->next->invokeEvent($event, $executor, $socket, $context);
        }
    }
}
