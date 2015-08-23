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
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class IoStage
 */
class IoStage extends AbstractTimeAwareStage
{
    /**
     * Handlers for processing I/O
     *
     * @var IoHandlerInterface[]
     */
    private $ioHandlers;

    /**
     * IoStage constructor.
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $eventCaller Event caller
     * @param IoHandlerInterface[]     $ioHandlers Array of operation handlers
     */
    public function __construct(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        array $ioHandlers
    ) {
        parent::__construct($executor, $eventCaller);
        $this->ioHandlers = $ioHandlers;
    }

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

            $handler       = $this->requireIoHandler($item);
            $nextOperation = $this->handleIoOperation($item, $handler);
            $isComplete    = $this->resolveNextOperation($item, $nextOperation);

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
     * @return IoHandlerInterface Flag, whether operation is complete
     * @throws \LogicException
     */
    private function requireIoHandler(OperationMetadata $operationMetadata)
    {
        $operation = $operationMetadata->getOperation();
        foreach ($this->ioHandlers as $handler) {
            if ($handler->supports($operation)) {
                return $handler;
            }
        }

        throw new \LogicException('There is no handler able to process ' . get_class($operation) . ' operation.');
    }

    /**
     * handleIoOperation
     *
     * @param OperationMetadata  $operationMetadata
     * @param IoHandlerInterface $ioHandler
     *
     * @return OperationInterface|null
     */
    private function handleIoOperation(OperationMetadata $operationMetadata, IoHandlerInterface $ioHandler)
    {
        try {
            $this->eventCaller->setCurrentOperation($operationMetadata);
            $result = $ioHandler->handle(
                $operationMetadata->getOperation(),
                $operationMetadata->getSocket(),
                $this->executor,
                $this->eventCaller
            );
            $this->eventCaller->clearCurrentOperation();

            return $result;
        } catch (NetworkSocketException $e) {
            $this->callExceptionSubscribers($operationMetadata, $e);
            return null;
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

        if ($operationMetadata->getOperation() === $nextOperation) {
            return false;
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
                $this->callExceptionSubscribers($operationMetadata, $e);
                return false;
            }
        }

        return true;
    }
}
