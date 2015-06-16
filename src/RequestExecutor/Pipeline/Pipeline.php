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
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\StopRequestExecuteException;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class Pipeline
 */
class Pipeline implements EventHandlerInterface
{
    /**
     * Flag, indicating stopping request
     *
     * @var bool
     */
    private $isRequestStopped = false;

    /**
     * Flag, indicating stopping request is in progress
     *
     * @var bool
     */
    private $isRequestStopInProgress = false;

    /**
     * DisconnectStage
     *
     * @var PipelineStageInterface
     */
    private $disconnectStage;

    /**
     * Connect stage
     *
     * @var PipelineStageInterface
     */
    private $connectStage;

    /**
     * PipelineStageInterface
     *
     * @var PipelineStageInterface[]
     */
    private $stages;

    /**
     * Pipeline constructor
     *
     * @param PipelineStageInterface   $connectStage Connect stage
     * @param PipelineStageInterface[] $stages Pipeline stages
     * @param PipelineStageInterface   $disconnectStage Disconnect stages
     */
    public function __construct(
        PipelineStageInterface $connectStage,
        array $stages,
        PipelineStageInterface $disconnectStage
    ) {
        $this->connectStage    = $connectStage;
        $this->stages          = $stages;
        $this->disconnectStage = $disconnectStage;
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        if ($this->isRequestStopped && !$this->isRequestStopInProgress) {
            $this->isRequestStopInProgress = true;
            throw new StopRequestExecuteException();
        }
    }

    /**
     * Process I/O operations on sockets
     *
     * @param SocketBag   $socketBag Socket bag
     * @param EventCaller $eventCaller Event caller
     *
     * @return void
     * @throws \Exception
     */
    public function process(SocketBag $socketBag, EventCaller $eventCaller)
    {
        $this->isRequestStopInProgress = false;
        $this->isRequestStopped        = false;

        try {
            $this->processMainExecutionLoop($socketBag);
        } catch (StopRequestExecuteException $e) {
            $this->isRequestStopInProgress = true;
            $this->disconnectStage->processStage($socketBag->getItems());
        } catch (SocketException $e) {
            foreach ($socketBag->getItems() as $item) {
                $eventCaller->callExceptionSubscribers($item, $e, null);
            }

            $this->disconnectStage->processStage($socketBag->getItems());
        } catch (\Exception $e) {
            $this->emergencyShutdown($socketBag);
            throw $e;
        }
    }

    /**
     * Start Pipeline cycle
     *
     * @param SocketBag $socketBag Socket bag
     *
     * @return void
     */
    private function processMainExecutionLoop(SocketBag $socketBag)
    {
        do {
            $activeOperations = $this->connectStage->processStage($socketBag->getItems());
            if (!$activeOperations) {
                break;
            }

            foreach ($this->stages as $stage) {
                $activeOperations = $stage->processStage($activeOperations);
            }
        } while (true);
    }

    /**
     * Stop execution for all registered sockets
     *
     * @return void
     */
    public function stopRequest()
    {
        $this->isRequestStopped = true;
    }

    /**
     * Shutdown all sockets in case of unhandled exception
     *
     * @param SocketBag $socketBag Socket bag
     *
     * @return void
     */
    private function emergencyShutdown(SocketBag $socketBag)
    {
        foreach ($socketBag->getItems() as $item) {
            try {
                $item->getSocket()->close();
            } catch (\Exception $e) {
                // nothing required
            }

            $item->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
        }
    }
}
