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
use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Class DisconnectStage
 */
class DisconnectStage extends AbstractStage
{
    /**
     * Selector
     *
     * @var AsyncSelector
     */
    private $selector;

    /**
     * DisconnectStage constructor.
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $eventCaller Event caller
     * @param AsyncSelector            $selector Async selector
     */
    public function __construct(RequestExecutorInterface $executor, EventCaller $eventCaller, AsyncSelector $selector)
    {
        parent::__construct($executor, $eventCaller);
        $this->selector = $selector;
    }

    /**
     * Disconnect array of sockets by given keys
     *
     * @param OperationMetadata[] $operations Array of operations to perform disconnect
     *
     * @return void
     */
    public function disconnectSockets(array $operations)
    {
        foreach ($operations as $operation) {
            $this->disconnectSingleSocket($operation);
        }
    }

    /**
     * Disconnect given socket
     *
     * @param OperationMetadata $operation Operation object
     *
     * @return void
     */
    private function disconnectSingleSocket(OperationMetadata $operation)
    {
        $meta = $operation->getMetadata();

        if ($meta[RequestExecutorInterface::META_REQUEST_COMPLETE]) {
            return;
        }

        $operation->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);

        $socket = $operation->getSocket();
        $event  = new Event(
            $this->executor,
            $socket,
            $meta[RequestExecutorInterface::META_USER_CONTEXT],
            EventType::DISCONNECTED
        );

        try {
            $socket->close();
            if ($meta[ RequestExecutorInterface::META_CONNECTION_FINISH_TIME ] !== null) {
                $this->callSocketSubscribers($operation, $event);
            }
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($operation, $e, $event);
        }

        $this->selector->removeAllSocketOperations($operation);
        $this->callSocketSubscribers(
            $operation,
            new Event($this->executor, $socket, $meta[RequestExecutorInterface::META_USER_CONTEXT], EventType::FINALIZE)
        );
    }
}
