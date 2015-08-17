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
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class AbstractStage
 */
abstract class AbstractStage implements PipelineStageInterface
{
    /**
     * Event caller
     *
     * @var EventCaller
     */
    protected $eventCaller;

    /**
     * Request executor
     *
     * @var RequestExecutorInterface
     */
    protected $executor;

    /**
     * AbstractStage constructor.
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $eventCaller Event caller
     */
    public function __construct(RequestExecutorInterface $executor, EventCaller $eventCaller)
    {
        $this->executor    = $executor;
        $this->eventCaller = $eventCaller;
    }

    /**
     * Notify handlers about given event
     *
     * @param OperationMetadata $operationMetadata Socket operation metadata
     * @param Event             $event Event object
     *
     * @return void
     * @throws \Exception
     */
    protected function callSocketSubscribers(OperationMetadata $operationMetadata, Event $event)
    {
        try {
            $this->eventCaller->setCurrentOperation($operationMetadata);
            $this->eventCaller->callSocketSubscribers($operationMetadata, $event);
        } catch (\Exception $e) {
            $this->eventCaller->clearCurrentOperation();
            throw $e;
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
     * @throws \Exception
     */
    protected function callExceptionSubscribers(
        OperationMetadata $operationMetadata,
        SocketException $exception,
        Event $event = null
    ) {
        try {
            $this->eventCaller->setCurrentOperation($operationMetadata);
            $this->eventCaller->callExceptionSubscribers($operationMetadata, $exception, $event);
            $this->eventCaller->clearCurrentOperation();
        } catch (\Exception $e) {
            $this->eventCaller->clearCurrentOperation();
            throw $e;
        }
    }

    /**
     * Create simple event
     *
     * @param OperationMetadata $operation Operation item
     * @param string            $eventName Event name for object
     *
     * @return Event
     */
    protected function createEvent(OperationMetadata $operation, $eventName)
    {
        $meta = $operation->getMetadata();

        return new Event(
            $this->executor,
            $operation->getSocket(),
            $meta[ RequestExecutorInterface::META_USER_CONTEXT ],
            $eventName
        );
    }
}
