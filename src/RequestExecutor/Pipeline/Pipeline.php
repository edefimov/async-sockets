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
use AsyncSockets\RequestExecutor\LimitationDeciderInterface;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\NoLimitationDecider;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

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
     * Limitation decider
     *
     * @var LimitationDeciderInterface
     */
    private $decider;

    /**
     * DisconnectStage
     *
     * @var DisconnectStage
     */
    private $disconnectStage;

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        if ($this->isRequestStopped && !$this->isRequestStopInProgress) {
            $this->isRequestStopInProgress = true;
            throw new StopRequestExecuteException();
        }
    }

    /**
     * Set decider for limiting running at once requests. You can additionally implement EventHandlerInterface
     * on your LimitationDecider to receive events from request executor
     *
     * @param LimitationDeciderInterface $decider New decider. If null, then NoLimitationDecider will be used
     *
     * @return void
     * @throws \BadMethodCallException When called on executing request
     * @see NoLimitationDecider
     * @see EventInvocationHandlerInterface
     */
    public function setLimitationDecider(LimitationDeciderInterface $decider = null)
    {
        $this->decider = $decider ?: new NoLimitationDecider();
    }

    /**
     * Process I/O operations on sockets
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param SocketBag                $socketBag Socket bag
     * @param EventCaller              $eventCaller Event caller
     *
     * @return void
     * @throws \Exception
     */
    public function process(RequestExecutorInterface $executor, SocketBag $socketBag, EventCaller $eventCaller)
    {
        $this->isRequestStopInProgress = false;
        $this->isRequestStopped        = false;
        $this->disconnectStage         = new DisconnectStage($executor, $eventCaller);
        $this->decider->initialize($executor);

        try {
            $this->processMainExecutionLoop($executor, $socketBag, $eventCaller);
            $this->disconnectStage->disconnectSockets($socketBag->getItems());
            $this->decider->finalize($executor);
        } catch (StopRequestExecuteException $e) {
            $this->isRequestStopInProgress = true;
            $this->disconnectStage->disconnectSockets($socketBag->getItems());
            $this->decider->finalize($executor);
            $this->disconnectStage = null;
        } catch (\Exception $e) {
            $this->emergencyShutdown($socketBag);
            $this->decider->finalize($executor);
            $this->disconnectStage = null;
            throw $e;
        }
    }

    /**
     * Start Pipeline cycle
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param SocketBag                $socketBag Socket bag
     * @param EventCaller              $eventCaller Event caller
     *
     * @return void
     */
    private function processMainExecutionLoop(
        RequestExecutorInterface $executor,
        SocketBag $socketBag,
        EventCaller $eventCaller
    ) {
        $selector = new AsyncSelector();
        foreach ($socketBag->getItems() as $item) {
            $item->initialize();
        }

        $connectStage = new ConnectStage($executor, $eventCaller, $this->decider);
        $selectStage  = new SelectStage($executor, $eventCaller);
        $ioStage      = new IoStage($executor, $eventCaller);


        do {
            $connectStage->processConnect($socketBag->getItems());
            $activeOperations = $this->getActiveOperations($socketBag);
            if (!$activeOperations) {
                break;
            }

            try {
                $context = $selectStage->processSelect($activeOperations, $selector);
                if ($context) {
                    $doneSockets = $ioStage->processIo($socketBag, $context);
                    $this->disconnectStage->disconnectSockets($doneSockets, $selector);
                    $activeOperations = array_diff_key($activeOperations, $doneSockets);
                }
            } catch (SocketException $e) {
                foreach ($socketBag->getItems() as $item) {
                    $eventCaller->callExceptionSubscribers($item, $e, null);
                }

                return;
            }

            $timeoutOperations = $selectStage->processTimeoutSockets($activeOperations);

            $this->disconnectStage->disconnectSockets($timeoutOperations, $selector);

            unset($doneSockets, $timeoutOperations);
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
     * Return array of keys for socket waiting for processing
     *
     * @param SocketBag $socketBag Socket bag object
     *
     * @return OperationMetadata[]
     */
    private function getActiveOperations(SocketBag $socketBag)
    {
        $result = [];
        foreach ($socketBag->getItems() as $key => $item) {
            $meta = $item->getMetadata();
            if (!$meta[RequestExecutorInterface::META_REQUEST_COMPLETE] && $item->isRunning()) {
                $result[$key] = $item;
            }
        }

        return $result;
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
