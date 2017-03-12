<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
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
     * @param RequestExecutorInterface $executor         Request executor
     * @param EventCaller              $eventCaller      Event caller
     * @param ExecutionContext         $executionContext Execution context
     * @param AsyncSelector            $selector         Async selector
     */
    public function __construct(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        ExecutionContext $executionContext,
        AsyncSelector $selector = null
    ) {
        parent::__construct($executor, $eventCaller, $executionContext);
        $this->selector = $selector;
    }

    /** {@inheritdoc} */
    public function processStage(array $requestDescriptors)
    {
        foreach ($requestDescriptors as $descriptor) {
            $this->disconnectSingleSocket($descriptor);
            $this->processForgottenDescriptor($descriptor);
        }

        return $requestDescriptors;
    }

    /**
     * Disconnect given socket
     *
     * @param RequestDescriptor $descriptor Operation object
     *
     * @return void
     */
    private function disconnectSingleSocket(RequestDescriptor $descriptor)
    {
        $meta = $descriptor->getMetadata();

        if ($meta[RequestExecutorInterface::META_REQUEST_COMPLETE]) {
            return;
        }

        if (!$this->isDisconnectRequired($descriptor)) {
            return;
        }

        $this->disconnect($descriptor);
    }

    /**
     * Disconnects given socket descriptor
     *
     * @param RequestDescriptor $descriptor Socket descriptor
     *
     * @return void
     */
    public function disconnect(RequestDescriptor $descriptor)
    {
        $meta   = $descriptor->getMetadata();
        $socket = $descriptor->getSocket();

        $descriptor->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
        try {
            $socket->close();
            if ($meta[ RequestExecutorInterface::META_CONNECTION_FINISH_TIME ] !== null) {
                $this->callSocketSubscribers(
                    $descriptor,
                    $this->createEvent($descriptor, EventType::DISCONNECTED)
                );
            }
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($descriptor, $e);
        }

        $this->dispatchFinalizeEvent($descriptor);
    }

    /**
     * Remove given descriptor from selector
     *
     * @param RequestDescriptor $operation
     *
     * @return void
     */
    private function removeOperationsFromSelector(RequestDescriptor $operation)
    {
        if ($this->selector) {
            $this->selector->removeAllSocketOperations($operation);
        }
    }

    /**
     * Check whether given socket should be disconnected
     *
     * @param RequestDescriptor $descriptor Socket descriptor
     *
     * @return bool
     */
    private function isDisconnectRequired(RequestDescriptor $descriptor)
    {
        $socket = $descriptor->getSocket();
        $meta   = $descriptor->getMetadata();

        return !$meta[RequestExecutorInterface::META_KEEP_ALIVE] || (
                feof($socket->getStreamResource()) !== false ||
                !stream_socket_get_name($socket->getStreamResource(), true)
            );
    }

    /**
     * Marks forgotten socket as complete
     *
     * @param RequestDescriptor $descriptor The descriptor
     *
     * @return void
     */
    private function processForgottenDescriptor(RequestDescriptor $descriptor)
    {
        $meta = $descriptor->getMetadata();
        if (!$meta[RequestExecutorInterface::META_KEEP_ALIVE] || !$descriptor->isForgotten()) {
            return;
        }

        $descriptor->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
        $this->dispatchFinalizeEvent($descriptor);
    }

    /**
     * Dispatches finalize event for socket
     *
     * @param RequestDescriptor $descriptor Descriptor for dispatching event
     *
     * @return void
     */
    private function dispatchFinalizeEvent(RequestDescriptor $descriptor)
    {
        $this->callSocketSubscribers(
            $descriptor,
            $this->createEvent($descriptor, EventType::FINALIZE)
        );

        $this->removeOperationsFromSelector($descriptor);
    }
}
