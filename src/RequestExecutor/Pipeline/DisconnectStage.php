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
     * Disconnect array of sockets by given keys
     *
     * @param OperationMetadata[] $operations Array of operations to perform disconnect
     * @param AsyncSelector       $selector Selector object
     *
     * @return void
     */
    public function disconnectSockets(array $operations, AsyncSelector $selector = null)
    {
        foreach ($operations as $operation) {
            $this->disconnectSingleSocket($operation, $selector);
        }
    }

    /**
     * Disconnect given socket
     *
     * @param OperationMetadata $operation Operation object
     * @param AsyncSelector     $selector Selector, which processing this socket
     *
     * @return void
     */
    private function disconnectSingleSocket(OperationMetadata $operation, AsyncSelector $selector = null)
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

        if ($selector) {
            $selector->removeAllSocketOperations($socket);
        }

        $this->callSocketSubscribers(
            $operation,
            new Event($this->executor, $socket, $meta[RequestExecutorInterface::META_USER_CONTEXT], EventType::FINALIZE)
        );
    }
}
