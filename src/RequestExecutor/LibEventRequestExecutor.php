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

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\RequestExecutor\LibEvent\LeBase;
use AsyncSockets\RequestExecutor\LibEvent\LeCallbackInterface;
use AsyncSockets\RequestExecutor\LibEvent\LeEvent;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface;
use AsyncSockets\RequestExecutor\Pipeline\StageFactoryInterface;
use AsyncSockets\RequestExecutor\Pipeline\TimeoutStage;
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
     * Delay stage
     *
     * @var PipelineStageInterface
     */
    private $delayStage;

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
     * Stage factory
     *
     * @var StageFactoryInterface
     */
    private $stageFactory;

    /**
     * Timeout stage
     *
     * @var TimeoutStage
     */
    private $timeoutStage;

    /**
     * Array of connected sockets information indexed by RequestDescriptor
     *
     * @var bool[]
     */
    private $connectedDescriptors = [];

    /**
     * LibEventRequestExecutor constructor.
     *
     * @param StageFactoryInterface $stageFactory Stage factory
     * @param Configuration   $configuration Configuration for executor
     */
    public function __construct(StageFactoryInterface $stageFactory, Configuration $configuration)
    {
        parent::__construct($configuration);
        $this->stageFactory = $stageFactory;
    }

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

        $this->connectStage    = $this->stageFactory->createConnectStage($this, $eventCaller, $this->solver);
        $this->ioStage         = $this->stageFactory->createIoStage($this, $eventCaller);
        $this->disconnectStage = $this->stageFactory->createDisconnectStage($this, $eventCaller);
        $this->delayStage      = $this->stageFactory->createDelayStage($this, $eventCaller);
        $this->timeoutStage    = new TimeoutStage($this, $eventCaller);
    }

    /** {@inheritdoc} */
    protected function terminateRequest()
    {
        parent::terminateRequest();
        $this->base        = null;

        $this->connectStage    = null;
        $this->ioStage         = null;
        $this->disconnectStage = null;
        $this->timeoutStage    = null;
    }

    /** {@inheritdoc} */
    protected function disconnectItems(array $items)
    {
        $this->disconnectStage->processStage($items);
    }

    /**
     * Setup libevent for given operation
     *
     * @param RequestDescriptor $descriptor Metadata object
     * @param int|null          $timeout Timeout in seconds
     *
     * @return void
     */
    private function setupEvent(RequestDescriptor $descriptor, $timeout)
    {
        $specification = new ConnectionLessSocketSpecification();
        if (!$specification->isSatisfiedBy($descriptor)) {
            $this->delayStage->processStage([$descriptor]);
            $event = new LeEvent($this, $descriptor, $timeout);
            $this->base->addEvent($event);
        } else {
            if ($this->delayStage->processStage([$descriptor])) {
                $this->onEvent($descriptor, LeCallbackInterface::EVENT_READ);
            }
        }
    }

    /** {@inheritdoc} */
    public function stopRequest()
    {
        parent::stopRequest();
        $this->base->breakLoop();
    }

    /** {@inheritdoc} */
    public function onEvent(RequestDescriptor $requestDescriptor, $type)
    {
        $doResetEvent = false;
        switch ($type) {
            case LeCallbackInterface::EVENT_READ:
                // fall down
            case LeCallbackInterface::EVENT_WRITE:
                $result       = $this->ioStage->processStage([ $requestDescriptor ]);
                $doResetEvent = empty($result);

                break;
            case LeCallbackInterface::EVENT_TIMEOUT:
                $doResetEvent = $this->timeoutStage->handleTimeoutOnDescriptor($requestDescriptor);
                break;
        }

        if ($doResetEvent) {
            $meta = $requestDescriptor->getMetadata();
            $this->setupEvent($requestDescriptor, $meta[self::META_IO_TIMEOUT]);
        } else {
            $this->disconnectStage->processStage([$requestDescriptor]);
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
            $this->setupEvent($item, $this->resolveTimeout($item));
        }
    }

    /**
     * Resolves timeout for setting up event
     *
     * @param RequestDescriptor $descriptor Descriptor object
     *
     * @return int
     */
    private function resolveTimeout(RequestDescriptor $descriptor)
    {
        $meta   = $descriptor->getMetadata();
        $key    = spl_object_hash($descriptor);
        $result = $meta[RequestExecutorInterface::META_IO_TIMEOUT];
        if (!isset($this->connectedDescriptors[$key])) {
            $result                           = $meta[RequestExecutorInterface::META_CONNECTION_TIMEOUT];
            $this->connectedDescriptors[$key] = true;
        }

        return $result;
    }
}
