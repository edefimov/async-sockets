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
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class AbstractStage
 */
abstract class AbstractStage
{
    /**
     * Event caller
     *
     * @var EventCaller
     */
    private $eventCaller;

    /**
     * Request executor
     *
     * @var RequestExecutorInterface
     */
    protected $executor;

    /**
     * Sockets for operations
     *
     * @var SocketBag
     */
    protected $socketBag;

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
     */
    protected function callSocketSubscribers(OperationMetadata $operationMetadata, Event $event)
    {
        $this->eventCaller->callSocketSubscribers($operationMetadata, $event);
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
    protected function callExceptionSubscribers(
        OperationMetadata $operationMetadata,
        SocketException $exception,
        Event $event = null
    ) {
        $this->eventCaller->callExceptionSubscribers($operationMetadata, $exception, $event);
    }
}
