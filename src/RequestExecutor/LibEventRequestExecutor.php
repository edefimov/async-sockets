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

use AsyncSockets\RequestExecutor\LibEvent\LeBase;
use AsyncSockets\RequestExecutor\LibEvent\LeCallbackInterface;
use AsyncSockets\RequestExecutor\LibEvent\LeEvent;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Pipeline\ConnectStage;
use AsyncSockets\RequestExecutor\Pipeline\DisconnectStage;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\IoStage;
use AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface;
use AsyncSockets\RequestExecutor\Pipeline\TimeoutStage;

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
     * Timeout stage
     *
     * @var PipelineStageInterface
     */
    private $timeoutStage;

    /**
     * Disconnect stage
     *
     * @var PipelineStageInterface
     */
    private $disconnectStage;

    /** {@inheritdoc} */
    protected function doExecuteRequest(EventCaller $eventCaller)
    {
        $this->base = new LeBase();

        $this->connectStage    = new ConnectStage($this, $eventCaller, $this->solver);
        $this->ioStage         = new IoStage($this, $eventCaller);
        $this->timeoutStage    = new TimeoutStage($this, $eventCaller);
        $this->disconnectStage = new DisconnectStage($this, $eventCaller);
        try {
            $this->connectSockets();

            $this->base->startLoop();
        } catch (\Exception $e) {
            $this->base    = null;
            $this->ioStage = null;
            throw $e;
        }

        $this->ioStage = null;
        $this->base    = null;
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
        $event = new LeEvent($this->base, $this);
        $event->register($operationMetadata, $timeout);
    }

    /** {@inheritdoc} */
    protected function doStopRequest()
    {
        $this->base->breakLoop();
    }

    /** {@inheritdoc} */
    public function onEvent(LeEvent $event, OperationMetadata $operationMetadata, $type)
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
                $this->timeoutStage->processStage([$operationMetadata]);
                break;
        }

        if ($doResetEvent) {
            $meta = $operationMetadata->getMetadata();
            $this->setupEvent($operationMetadata, $meta[self::META_IO_TIMEOUT]);
        } else {
            $this->disconnectStage->processStage([$operationMetadata]);
            $this->connectSockets();
        }
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
}
