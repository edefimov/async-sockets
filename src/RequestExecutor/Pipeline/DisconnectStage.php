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
use AsyncSockets\Socket\PersistentClientSocket;

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
    public function __construct(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        AsyncSelector $selector = null
    ) {
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

        $socket = $operation->getSocket();
        $event  = $this->createEvent($operation, EventType::DISCONNECTED);

        if (!($socket instanceof PersistentClientSocket)) {
            $operation->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);

            try {
                $socket->close();
                if ($meta[ RequestExecutorInterface::META_CONNECTION_FINISH_TIME ] !== null) {
                    $this->callSocketSubscribers($operation, $event);
                }
            } catch (SocketException $e) {
                $this->callExceptionSubscribers($operation, $e);
            }

            $this->callSocketSubscribers(
                $operation,
                $this->createEvent($operation, EventType::FINALIZE)
            );
        }

        $this->removeOperationsFromSelector($operation);
    }

    /**
     * Remove given operation from selector
     *
     * @param OperationMetadata $operation
     *
     * @return void
     */
    private function removeOperationsFromSelector(OperationMetadata $operation)
    {
        if ($this->selector) {
            $this->selector->removeAllSocketOperations($operation);
        }
    }
}
