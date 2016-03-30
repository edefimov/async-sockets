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
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
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
     * @param RequestDescriptor $requestDescriptor Request descriptor
     * @param Event             $event Event object
     *
     * @return void
     * @throws \Exception
     */
    protected function callSocketSubscribers(RequestDescriptor $requestDescriptor, Event $event)
    {
        try {
            $this->eventCaller->setCurrentOperation($requestDescriptor);
            $this->eventCaller->callSocketSubscribers($requestDescriptor, $event);
            $this->eventCaller->clearCurrentOperation();
        } catch (\Exception $e) {
            $this->eventCaller->clearCurrentOperation();
            throw $e;
        }
    }

    /**
     * Notify handlers about exception
     *
     * @param RequestDescriptor $requestDescriptor Socket operation object
     * @param SocketException   $exception Thrown exception
     *
     * @return void
     * @throws \Exception
     */
    protected function callExceptionSubscribers(
        RequestDescriptor $requestDescriptor,
        SocketException $exception
    ) {
        try {
            $this->eventCaller->setCurrentOperation($requestDescriptor);
            $this->eventCaller->callExceptionSubscribers($requestDescriptor, $exception);
            $this->eventCaller->clearCurrentOperation();
        } catch (\Exception $e) {
            $this->eventCaller->clearCurrentOperation();
            throw $e;
        }
    }

    /**
     * Create simple event
     *
     * @param RequestDescriptor $operation Operation item
     * @param string            $eventName Event name for object
     *
     * @return Event
     */
    protected function createEvent(RequestDescriptor $operation, $eventName)
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
