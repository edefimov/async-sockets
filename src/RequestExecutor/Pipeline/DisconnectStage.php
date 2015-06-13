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

    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        foreach ($operations as $operation) {
            $this->disconnectSingleSocket($operation);
        }

        return $operations;
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
        $event  = $this->createEvent($operation, EventType::DISCONNECTED);

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
            $this->createEvent($operation, EventType::FINALIZE)
        );
    }
}
