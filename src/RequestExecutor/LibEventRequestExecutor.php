<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\LibEvent\LeBase;
use AsyncSockets\RequestExecutor\LibEvent\LeCallbackInterface;
use AsyncSockets\RequestExecutor\LibEvent\LeEvent;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Pipeline\ConnectStage;
use AsyncSockets\RequestExecutor\Pipeline\DisconnectStage;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\IoStage;
use AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface;
use AsyncSockets\RequestExecutor\Pipeline\ReadIoHandler;
use AsyncSockets\RequestExecutor\Pipeline\SslHandshakeIoHandler;
use AsyncSockets\RequestExecutor\Pipeline\WriteIoHandler;
use AsyncSockets\RequestExecutor\Specification\ConnectionLessSocketSpecification;

/**
 * Class LibEventRequestExecutor
 */
class LibEventRequestExecutor extends AbstractRequestExecutor implements LeCallbackInterface
{
    /**
     * Libevent handle
     *
     * @var LeBase
     */
    private $base;

    /**
     * Connect stage
     *
     * @var PipelineStageInterface
     */
    private $connectStage;

    /**
     * I/O stage
     *
     * @var PipelineStageInterface
     */
    private $ioStage;

    /**
     * Disconnect stage
     *
     * @var PipelineStageInterface
     */
    private $disconnectStage;

    /**
     * EventCaller
     *
     * @var EventCaller
     */
    private $eventCaller;

    /** {@inheritdoc} */
    protected function doExecuteRequest(EventCaller $eventCaller)
    {
        $this->connectSockets();
        $this->base->startLoop();
    }

    /** {@inheritdoc} */
    protected function initializeRequest(EventCaller $eventCaller)
    {
        parent::initializeRequest($eventCaller);
        $this->base        = new LeBase();
        $this->eventCaller = $eventCaller;

        $this->connectStage    = new ConnectStage($this, $eventCaller, $this->solver);
        $this->ioStage         = new IoStage($this, $eventCaller, [
            new ReadIoHandler(),
            new WriteIoHandler(),
            new SslHandshakeIoHandler()
        ]);
        $this->disconnectStage = new DisconnectStage($this, $eventCaller);
    }

    /** {@inheritdoc} */
    protected function terminateRequest()
    {
        parent::terminateRequest();
        $this->base        = null;
        $this->eventCaller = null;

        $this->connectStage    = null;
        $this->ioStage         = null;
        $this->disconnectStage = null;
    }

    /** {@inheritdoc} */
    protected function disconnectItems(array $items)
    {
        $this->disconnectStage->processStage($items);
    }

    /**
     * Setup libevent for given operation
     *
     * @param OperationMetadata $operationMetadata Metadata object
     * @param int|null          $timeout Timeout in seconds
     *
     * @return void
     */
    private function setupEvent(OperationMetadata $operationMetadata, $timeout)
    {
        $specification = new ConnectionLessSocketSpecification();
        if (!$specification->isSatisfiedBy($operationMetadata)) {
            $this->base->addEvent(
                new LeEvent($this, $operationMetadata, $timeout)
            );
        } else {
            $this->onEvent($operationMetadata, LeCallbackInterface::EVENT_READ);
        }
    }

    /** {@inheritdoc} */
    public function stopRequest()
    {
        parent::stopRequest();
        $this->base->breakLoop();
    }

    /** {@inheritdoc} */
    public function onEvent(OperationMetadata $operationMetadata, $type)
    {
        $doResetEvent = false;
        switch ($type) {
            case LeCallbackInterface::EVENT_READ:
                // fall down
            case LeCallbackInterface::EVENT_WRITE:
                $result       = $this->ioStage->processStage([$operationMetadata]);
                $doResetEvent = empty($result);

                break;
            case LeCallbackInterface::EVENT_TIMEOUT:
                $this->handleTimeout($operationMetadata);
                break;
        }

        if ($doResetEvent) {
            $meta = $operationMetadata->getMetadata();
            $this->setupEvent($operationMetadata, $meta[self::META_IO_TIMEOUT]);
        } else {
            $this->disconnectStage->processStage([$operationMetadata]);
        }

        $this->connectSockets();
    }

    /**
     * Connect sockets to server
     *
     * @return void
     */
    private function connectSockets()
    {
        $items = $this->socketBag->getItems();
        foreach ($this->connectStage->processStage($items) as $item) {
            $meta = $item->getMetadata();
            $this->setupEvent($item, $meta[ self::META_CONNECTION_TIMEOUT ]);
        }
    }

    /**
     * Process timeout event on socket
     *
     * @param OperationMetadata $operationMetadata
     *
     * @return void
     */
    private function handleTimeout(OperationMetadata $operationMetadata)
    {
        $operation = $operationMetadata->getOperation();
        $socket    = $operationMetadata->getSocket();
        $event     = new Event($this, $socket, $operation, EventType::TIMEOUT);
        try {
            $this->eventCaller->callSocketSubscribers($operationMetadata, $event);
        } catch (SocketException $e) {
            $this->eventCaller->callExceptionSubscribers($operationMetadata, $e);
        }
    }
}
