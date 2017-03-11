<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
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
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

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
     * RequestDescriptor
     *
     * @var RequestDescriptor
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
     * @param RequestDescriptor $currentOperation New value for CurrentOperation
     *
     * @return void
     */
    public function setCurrentOperation(RequestDescriptor $currentOperation)
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
    public function invokeEvent(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        $this->callSocketSubscribers($this->currentOperation, $event, $executor, $context);
    }

    /**
     * Notify handlers about given event
     *
     * @param RequestDescriptor        $requestDescriptor Request descriptor
     * @param Event                    $event             Event object
     * @param RequestExecutorInterface $executor          Request executor
     * @param ExecutionContext         $context           Execution context
     *
     * @return void
     */
    public function callSocketSubscribers(
        RequestDescriptor $requestDescriptor,
        Event $event,
        RequestExecutorInterface $executor,
        ExecutionContext $context
    ) {
        $requestDescriptor->invokeEvent($event, $executor, $requestDescriptor->getSocket(), $context);

        foreach ($this->handlers as $handler) {
            $handler->invokeEvent($event, $executor, $requestDescriptor->getSocket(), $context);
        }

        if ($event->isOperationCancelled()) {
            throw new StopSocketOperationException();
        }
    }

    /**
     * Notify handlers about exception
     *
     * @param RequestDescriptor        $requestDescriptor Socket operation object
     * @param SocketException          $exception         Thrown exception
     * @param RequestExecutorInterface $executor          Request executor
     * @param ExecutionContext         $context           Execution context
     *
     * @return void
     */
    public function callExceptionSubscribers(
        RequestDescriptor $requestDescriptor,
        SocketException $exception,
        RequestExecutorInterface $executor,
        ExecutionContext $context
    ) {
        if ($exception instanceof StopSocketOperationException) {
            return;
        }

        $meta           = $requestDescriptor->getMetadata();
        $exceptionEvent = new SocketExceptionEvent(
            $exception,
            $this->executor,
            $requestDescriptor->getSocket(),
            $meta[RequestExecutorInterface::META_USER_CONTEXT]
        );
        $this->callSocketSubscribers($requestDescriptor, $exceptionEvent, $executor, $context);
    }
}
