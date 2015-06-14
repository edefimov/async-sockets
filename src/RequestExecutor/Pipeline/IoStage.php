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

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AcceptResponse;
use AsyncSockets\Socket\ChunkSocketResponse;

/**
 * Class IoStage
 */
class IoStage extends AbstractTimeAwareStage
{
    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        /** @var OperationMetadata[] $operations */
        $result = [];
        foreach ($operations as $item) {
            if (!$this->setConnectionFinishTime($item)) {
                $result[] = $item;
                continue;
            }

            $isComplete = $this->processIoOperation($item);

            if ($isComplete) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Resolves I/O operation type and process it
     *
     * @param OperationMetadata $operation Operation object
     *
     * @return bool Flag, whether operation is complete
     */
    private function processIoOperation(OperationMetadata $operation)
    {
        switch ($operation->getOperation()->getType()) {
            case RequestExecutorInterface::OPERATION_READ:
                return $this->processReadIo($operation);
            case RequestExecutorInterface::OPERATION_WRITE:
                return $this->processWriteIo($operation);
            default:
                return true;
        }
    }

    /**
     * Process reading operation
     *
     * @param OperationMetadata $operationMetadata Metadata
     *
     * @return bool Flag, whether operation is complete
     */
    private function processReadIo(OperationMetadata $operationMetadata)
    {
        $meta      = $operationMetadata->getMetadata();
        $socket    = $operationMetadata->getSocket();
        $operation = $operationMetadata->getOperation();
        $context   = $meta[ RequestExecutorInterface::META_USER_CONTEXT ];
        $event     = null;

        try {
            /** @var ReadOperation $operation */
            $response = $socket->read($operation->getFramePicker(), $operationMetadata->getPreviousResponse());
            switch (true) {
                case $response instanceof ChunkSocketResponse:
                    $operationMetadata->setPreviousResponse($response);
                    return false;
                case $response instanceof AcceptResponse:
                    $event = new AcceptEvent(
                        $this->executor,
                        $socket,
                        $context,
                        $response->getClientSocket(),
                        $response->getClientAddress()
                    );

                    $this->callSocketSubscribers($operationMetadata, $event);
                    return $this->resolveNextOperation($operationMetadata, new ReadOperation());
                default:
                    $event = new ReadEvent(
                        $this->executor,
                        $socket,
                        $context,
                        $response
                    );

                    $this->callSocketSubscribers($operationMetadata, $event);
                    return $this->resolveNextOperation($operationMetadata, $event->getNextOperation());
            }
        } catch (AcceptException $e) {
            return $this->resolveNextOperation($operationMetadata, new ReadOperation());
        } catch (SocketException $e) {
            $this->callExceptionSubscribers(
                $operationMetadata,
                $e,
                $event ?: new ReadEvent($this->executor, $socket, $context)
            );

            return true;
        }
    }

    /**
     * Process write operation
     *
     * @param OperationMetadata $operationMetadata Metadata
     *
     * @return bool Flag, whether operation is complete
     */
    private function processWriteIo(OperationMetadata $operationMetadata)
    {
        $meta   = $operationMetadata->getMetadata();
        $socket = $operationMetadata->getSocket();
        $event  = new WriteEvent(
            $operationMetadata->getOperation(),
            $this->executor,
            $socket,
            $meta[ RequestExecutorInterface::META_USER_CONTEXT ]
        );
        try {
            $this->callSocketSubscribers($operationMetadata, $event);
            if ($event->getOperation()->hasData()) {
                $socket->write($event->getOperation()->getData());
            }

            return $this->resolveNextOperation($operationMetadata, $event->getNextOperation());
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($operationMetadata, $e, $event);
            return true;
        }
    }

    /**
     * Fill next operation in given object and return flag indicating whether operation is required
     *
     * @param OperationMetadata  $operationMetadata Operation metadata object
     * @param OperationInterface $nextOperation Next operation object
     *
     * @return bool True if given operation is complete
     */
    private function resolveNextOperation(
        OperationMetadata $operationMetadata,
        OperationInterface $nextOperation = null
    ) {
        if (!$nextOperation) {
            return true;
        }

        $operationMetadata->setOperation($nextOperation);
        $operationMetadata->setMetadata(
            [
                RequestExecutorInterface::META_LAST_IO_START_TIME => null,
            ]
        );

        return false;
    }

    /**
     * Set connection finish time and fire socket if it was not connected
     *
     * @param OperationMetadata $operationMetadata
     *
     * @return bool True, if there was no error, false if operation should be stopped
     */
    private function setConnectionFinishTime(OperationMetadata $operationMetadata)
    {
        $meta         = $operationMetadata->getMetadata();
        $wasConnected = $meta[ RequestExecutorInterface::META_CONNECTION_FINISH_TIME ] !== null;
        $this->setSocketOperationTime($operationMetadata, RequestExecutorInterface::META_CONNECTION_FINISH_TIME);
        if (!$wasConnected) {
            $event = $this->createEvent($operationMetadata, EventType::CONNECTED);

            try {
                $this->callSocketSubscribers($operationMetadata, $event);
            } catch (SocketException $e) {
                $this->callExceptionSubscribers($operationMetadata, $e, $event);
                return false;
            }
        }

        return true;
    }
}
