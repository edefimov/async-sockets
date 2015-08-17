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
class EventCaller implements EventHandlerInterface
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
     * OperationMetadata
     *
     * @var OperationMetadata
     */
    private $currentOperation;

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
     * Sets CurrentOperation
     *
     * @param OperationMetadata $currentOperation New value for CurrentOperation
     *
     * @return void
     */
    public function setCurrentOperation(OperationMetadata $currentOperation)
    {
        $this->currentOperation = $currentOperation;
    }

    /**
     * Clear current operation object
     *
     * @return void
     */
    public function clearCurrentOperation()
    {
        $this->currentOperation = null;
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        $this->callSocketSubscribers($this->currentOperation, $event);
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
        $operationMetadata->invokeEvent($event);

        foreach ($this->handlers as $handler) {
            $handler->invokeEvent($event);
        }

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
