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
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\StopSocketOperationException;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class EventCaller
 */
class EventCaller
{
    /**
     * List of handlers
     *
     * @var EventHandlerInterface[]
     */
    private $handlers = [];

    /**
     * Request executor
     *
     * @var RequestExecutorInterface
     */
    private $executor;

    /**
     * EventCaller constructor.
     *
     * @param RequestExecutorInterface          $executor Request executor
     */
    public function __construct(RequestExecutorInterface $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Add given handler to list
     *
     * @param EventHandlerInterface $handler
     *
     * @return void
     */
    public function addHandler(EventHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Notify handlers about given event
     *
     * @param OperationMetadata $operationMetadata Socket operation metadata
     * @param Event             $event Event object
     *
     * @return void
     * @throws StopSocketOperationException
     */
    public function callSocketSubscribers(OperationMetadata $operationMetadata, Event $event)
    {
        $socketHandlers = $operationMetadata->getEventInvocationHandler();
        if ($socketHandlers) {
            $socketHandlers->invokeEvent($event);
        }

        foreach ($this->handlers as $handler) {
            $handler->invokeEvent($event);
        }

        //$this->handleSocketEvent($socket, $event);

        if ($event->isOperationCancelled()) {
            throw new StopSocketOperationException();
        }
    }

    /**
     * Notify handlers about exception
     *
     * @param OperationMetadata $operationMetadata Socket operation object
     * @param SocketException $exception Thrown exception
     * @param Event           $event Event object
     *
     * @return void
     */
    public function callExceptionSubscribers(
        OperationMetadata $operationMetadata,
        SocketException $exception,
        Event $event = null
    ) {
        if ($exception instanceof StopSocketOperationException) {
            return;
        }

        $meta           = $operationMetadata->getMetadata();
        $exceptionEvent = new SocketExceptionEvent(
            $exception,
            $this->executor,
            $operationMetadata->getSocket(),
            $meta[RequestExecutorInterface::META_USER_CONTEXT],
            $event
        );
        $this->callSocketSubscribers($operationMetadata, $exceptionEvent);
    }
}
