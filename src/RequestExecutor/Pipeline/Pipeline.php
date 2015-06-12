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
     * @var DisconnectStage
     */
    private $disconnectStage;

    /**
     * Connect stage
     *
     * @var ConnectStage
     */
    private $connectStage;

    /**
     * Select stage
     *
     * @var SelectStage
     */
    private $selectStage;

    /**
     * I/O stage
     *
     * @var IoStage
     */
    private $ioStage;

    /**
     * Pipeline constructor
     *
     * @param ConnectStage    $connectStage Connect stage
     * @param SelectStage     $selectStage Select stage
     * @param IoStage         $ioStage I/O stage
     * @param DisconnectStage $disconnectStage Disconnect stage
     */
    public function __construct(
        ConnectStage $connectStage,
        SelectStage $selectStage,
        IoStage $ioStage,
        DisconnectStage $disconnectStage
    ) {
        $this->connectStage    = $connectStage;
        $this->selectStage     = $selectStage;
        $this->ioStage         = $ioStage;
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
            $this->processMainExecutionLoop($socketBag, $eventCaller);
        } catch (StopRequestExecuteException $e) {
            $this->isRequestStopInProgress = true;
            $this->disconnectStage->disconnectSockets($socketBag->getItems());
        } catch (\Exception $e) {
            $this->emergencyShutdown($socketBag);
            throw $e;
        }
    }

    /**
     * Start Pipeline cycle
     *
     * @param SocketBag   $socketBag Socket bag
     * @param EventCaller $eventCaller Event caller
     *
     * @return void
     */
    private function processMainExecutionLoop(SocketBag $socketBag, EventCaller $eventCaller)
    {
        foreach ($socketBag->getItems() as $item) {
            $item->initialize();
        }

        do {
            try {
                $activeOperations = $this->connectStage->processConnect($socketBag);
                if (!$activeOperations) {
                    break;
                }

                $context     = $this->selectStage->processSelect($activeOperations);
                $doneSockets = $this->ioStage->processIo($context);
                $this->disconnectStage->disconnectSockets($doneSockets);
                foreach ($activeOperations as $key => $socket) {
                    if (in_array($socket, $doneSockets, true)) {
                        unset($activeOperations[$key]);
                    }
                }

                $timeoutOperations = $this->selectStage->processTimeoutSockets($activeOperations);

                $this->disconnectStage->disconnectSockets($timeoutOperations);

                unset($doneSockets, $timeoutOperations);
            } catch (SocketException $e) {
                foreach ($socketBag->getItems() as $item) {
                    $eventCaller->callExceptionSubscribers($item, $e, null);
                }

                return;
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
