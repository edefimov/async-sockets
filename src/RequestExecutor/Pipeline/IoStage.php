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
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\InProgressWriteOperation;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
use AsyncSockets\Socket\SocketInterface;

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
     * @param OperationMetadata $operationMetadata Operation object
     *
     * @return bool Flag, whether operation is complete
     */
    private function processIoOperation(OperationMetadata $operationMetadata)
    {
        switch ($operationMetadata->getOperation()->getType()) {
            case OperationInterface::OPERATION_READ:
                return $this->processReadIo($operationMetadata);
            case OperationInterface::OPERATION_WRITE:
                return $this->processWriteIo($operationMetadata);
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
            $response = $socket->read($operation->getFramePicker());
            switch (true) {
                case $response instanceof PartialFrame:
                    return false;
                case $response instanceof AcceptedFrame:
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
                $event ?: new ReadEvent($this->executor, $socket, $context, new PartialFrame(new Frame('')))
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
        $meta      = $operationMetadata->getMetadata();
        $socket    = $operationMetadata->getSocket();
        $operation = $operationMetadata->getOperation();
        $fireEvent = !($operation instanceof InProgressWriteOperation);

        /** @var WriteOperation $operation */
        $event = new WriteEvent(
            $operation,
            $this->executor,
            $socket,
            $meta[ RequestExecutorInterface::META_USER_CONTEXT ]
        );
        try {
            if ($fireEvent) {
                $this->callSocketSubscribers($operationMetadata, $event);
                $nextOperation = $event->getNextOperation();
            } else {
                $nextOperation = $operation;
            }

            $nextOperation = $this->writeDataToSocket($operation, $socket, $nextOperation);

            return $this->resolveNextOperation($operationMetadata, $nextOperation);
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($operationMetadata, $e, $event);
            return true;
        }
    }

    /**
     * Marks write operation as in progress
     *
     * @param WriteOperation     $operation Current operation object
     * @param OperationInterface $nextOperation Next planned operation
     *
     * @return InProgressWriteOperation Next operation object
     */
    private function makeInProgressWriteOperation(WriteOperation $operation, OperationInterface $nextOperation = null)
    {
        $result = $operation;
        if (!($result instanceof InProgressWriteOperation)) {
            $result = new InProgressWriteOperation($nextOperation, $operation->getData());
        }

        return $result;
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

    /**
     * Perform data writing to socket and return suitable next socket operation
     *
     * @param WriteOperation     $operation Current write operation instance
     * @param SocketInterface    $socket Socket object
     * @param OperationInterface $nextOperation Desirable next operation
     *
     * @return OperationInterface Actual next operation
     */
    private function writeDataToSocket(
        WriteOperation $operation,
        SocketInterface $socket,
        OperationInterface $nextOperation = null
    ) {
        $result               = $nextOperation;
        $extractNextOperation = true;
        if ($operation->hasData()) {
            $data    = $operation->getData();
            $length  = strlen($data);
            $written = $socket->write($data);
            if ($length !== $written) {
                $extractNextOperation = false;

                $operation = $this->makeInProgressWriteOperation($operation, $nextOperation);
                $operation->setData(
                    substr($operation->getData(), $written)
                );

                $result = $operation;
            }
        }

        if ($extractNextOperation && ($operation instanceof InProgressWriteOperation)) {
            /** @var InProgressWriteOperation $operation */
            $result = $operation->getNextOperation();
        }

        return $result;
    }
}
