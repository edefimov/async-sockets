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
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
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
    public function processStage(array $requestDescriptors)
    {
        foreach ($requestDescriptors as $descriptor) {
            $this->disconnectSingleSocket($descriptor);
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

        $socket = $descriptor->getSocket();

        $isTimeToLeave = (
                            ($socket instanceof PersistentClientSocket) &&
                            (
                                feof($socket->getStreamResource()) !== false ||
                                !stream_socket_get_name($socket->getStreamResource(), true)
                            )
                         ) || (
                            !($socket instanceof PersistentClientSocket)
                         );

        if (!$isTimeToLeave) {
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

        $this->callSocketSubscribers(
            $descriptor,
            $this->createEvent($descriptor, EventType::FINALIZE)
        );

        $this->removeOperationsFromSelector($descriptor);
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
}
