<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Operation\NullOperation;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\Operation\OperationInterface;
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
    public function processStage(array $requestDescriptors)
    {
        /** @var RequestDescriptor[] $requestDescriptors */
        $result = [];
        foreach ($requestDescriptors as $descriptor) {
            if (!$this->setConnectionFinishTime($descriptor)) {
                $result[] = $descriptor;
                continue;
            }

            $handler       = $this->requireIoHandler($descriptor);
            $nextOperation = $this->handleIoOperation($descriptor, $handler);
            $isComplete    = $this->resolveNextOperation($descriptor, $nextOperation);

            if ($isComplete) {
                $result[] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * Resolves I/O operation type and process it
     *
     * @param RequestDescriptor $requestDescriptor Operation object
     *
     * @return IoHandlerInterface Flag, whether operation is complete
     * @throws \LogicException
     */
    private function requireIoHandler(RequestDescriptor $requestDescriptor)
    {
        $operation = $requestDescriptor->getOperation();
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
     * @param RequestDescriptor  $requestDescriptor
     * @param IoHandlerInterface $ioHandler
     *
     * @return OperationInterface
     */
    private function handleIoOperation(RequestDescriptor $requestDescriptor, IoHandlerInterface $ioHandler)
    {
        try {
            $this->eventCaller->setCurrentOperation($requestDescriptor);
            $result = $ioHandler->handle(
                $requestDescriptor,
                $this->executor,
                $this->eventCaller
            );
            $this->eventCaller->clearCurrentOperation();

            return $result ?: NullOperation::getInstance();
        } catch (NetworkSocketException $e) {
            $this->callExceptionSubscribers($requestDescriptor, $e);
            return NullOperation::getInstance();
        }
    }

    /**
     * Fill next operation in given object and return flag indicating whether operation is required
     *
     * @param RequestDescriptor  $requestDescriptor Request descriptor object
     * @param OperationInterface $nextOperation Next operation object
     *
     * @return bool True if given operation is complete
     */
    private function resolveNextOperation(
        RequestDescriptor $requestDescriptor,
        OperationInterface $nextOperation
    ) {
        if ($nextOperation instanceof NullOperation) {
            $requestDescriptor->setOperation($nextOperation);
            return true;
        }

        if ($requestDescriptor->getOperation() === $nextOperation) {
            return false;
        }

        $requestDescriptor->setOperation($nextOperation);
        $requestDescriptor->setMetadata(
            [
                RequestExecutorInterface::META_LAST_IO_START_TIME => null,
            ]
        );

        return false;
    }

    /**
     * Set connection finish time and fire socket if it was not connected
     *
     * @param RequestDescriptor $requestDescriptor
     *
     * @return bool True, if there was no error, false if operation should be stopped
     */
    private function setConnectionFinishTime(RequestDescriptor $requestDescriptor)
    {
        $meta         = $requestDescriptor->getMetadata();
        $wasConnected = $meta[ RequestExecutorInterface::META_CONNECTION_FINISH_TIME ] !== null;
        $this->setSocketOperationTime($requestDescriptor, RequestExecutorInterface::META_CONNECTION_FINISH_TIME);
        if (!$wasConnected) {
            $event = $this->createEvent($requestDescriptor, EventType::CONNECTED);

            try {
                $this->callSocketSubscribers($requestDescriptor, $event);
            } catch (SocketException $e) {
                $this->callExceptionSubscribers($requestDescriptor, $e);
                return false;
            }
        }

        return true;
    }
}
