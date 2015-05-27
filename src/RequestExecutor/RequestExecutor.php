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
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\StopRequestExecuteException;
use AsyncSockets\Exception\StopSocketOperationException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\ChunkSocketResponse;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class RequestExecutor
 */
class RequestExecutor implements RequestExecutorInterface
{
    /**
     * Io was completely done
     */
    const IO_STATE_DONE = 0;

    /**
     * Partial i/o result
     */
    const IO_STATE_PARTIAL = 1;

    /**
     * Exception during I/O processing
     */
    const IO_STATE_EXCEPTION = 2;

    /**
     * Flag whether we are executing request
     *
     * @var bool
     */
    private $isExecuting = false;

    /**
     * Flag, indicating stopping request
     *
     * @var bool
     */
    private $isRequestStopped;

    /**
     * Flag, indicating stopping request is in progress
     *
     * @var bool
     */
    private $isRequestStopInProgress;

    /**
     * Decider for request limitation
     *
     * @var LimitationDeciderInterface
     */
    private $decider;

    /**
     * EventInvocationHandlerInterface
     *
     * @var EventInvocationHandlerInterface
     */
    private $eventInvocationHandler;

    /**
     * Socket bag
     *
     * @var SocketBag
     */
    private $socketBag;

    /**
     * RequestExecutor constructor.
     */
    public function __construct()
    {
        $this->socketBag = new SocketBag($this);
    }

    /** {@inheritdoc} */
    public function getSocketBag()
    {
        return $this->socketBag;
    }


    /** {@inheritdoc} */
    public function setEventInvocationHandler(EventInvocationHandlerInterface $handler = null)
    {
        $this->eventInvocationHandler = $handler;
    }

    /** {@inheritdoc} */
    public function isExecuting()
    {
        return $this->isExecuting;
    }

    /** {@inheritdoc} */
    public function executeRequest()
    {
        if ($this->isExecuting()) {
            throw new \LogicException('Request is already in progress');
        }

        $this->initializeRequest();

        try {
            $this->processMainExecutionLoop();
            $this->disconnectSockets($this->socketBag->getItems());
            $this->finalizeRequest();
        } catch (StopRequestExecuteException $e) {
            $this->isRequestStopInProgress = true;
            $this->disconnectSockets($this->socketBag->getItems());
            $this->finalizeRequest();
        } catch (\Exception $e) {
            $this->emergencyShutdown();
            $this->finalizeRequest();
            throw $e;
        }
    }

    /** {@inheritdoc} */
    public function stopRequest()
    {
        if (!$this->isExecuting()) {
            throw new \BadMethodCallException('Can not stop inactive request');
        }

        $this->isRequestStopped = true;
    }

    /**
     * Process connect phase
     *
     * @return void
     */
    private function processConnect()
    {
        foreach ($this->socketBag->getItems() as $item) {
            $decision = $this->decide($item);
            if ($decision === LimitationDeciderInterface::DECISION_PROCESS_SCHEDULED) {
                break;
            } elseif ($decision === LimitationDeciderInterface::DECISION_SKIP_CURRENT) {
                continue;
            } elseif ($decision !== LimitationDeciderInterface::DECISION_OK) {
                throw new \LogicException('Unknown decision ' . $decision . ' received.');
            }

            $socket = $item->getSocket();
            $meta   = $item->getMetadata();
            $event  = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::INITIALIZE);

            try {
                $this->callSocketSubscribers($socket, $event);
                $this->setSocketOperationTime($item, self::META_CONNECTION_START_TIME);
                $socket->setBlocking(false);
                if (!$socket->getStreamResource()) {
                    $streamContext = $this->getStreamContextFromMetaData($meta);
                    $socket->open($meta[self::META_ADDRESS], $streamContext);
                }
                $item->setRunning(true);
            } catch (SocketException $e) {
                $item->setMetadata(self::META_REQUEST_COMPLETE, true);
                $this->callExceptionSubscribers($socket, $e, $event);
            }
        }
    }

    /**
     * Decide how to process given operation
     *
     * @param OperationMetadata $operationMetadata Operation to decide
     *
     * @return int One of LimitationDeciderInterface::DECISION_* consts
     */
    private function decide(OperationMetadata $operationMetadata)
    {
        if ($operationMetadata->isRunning()) {
            return LimitationDeciderInterface::DECISION_SKIP_CURRENT;
        }

        $decision = $this->decider->decide($this, $operationMetadata->getSocket(), count($this->socketBag));
        if ($decision !== LimitationDeciderInterface::DECISION_OK) {
            return $decision;
        }

        $meta           = $operationMetadata->getMetadata();
        $isSkippingThis = ($meta[self::META_CONNECTION_START_TIME] !== null || $meta[self::META_REQUEST_COMPLETE]);
        if ($isSkippingThis) {
            return LimitationDeciderInterface::DECISION_SKIP_CURRENT;
        }

        return LimitationDeciderInterface::DECISION_OK;
    }

    /**
     * Process I/O operations on sockets
     *
     * @return void
     */
    private function processMainExecutionLoop()
    {
        $selector = new AsyncSelector();
        foreach ($this->socketBag->getItems() as $item) {
            $item->initialize();
        }

        do {
            $this->processConnect();
            $activeOperations = $this->getActiveOperations();
            if (!$activeOperations) {
                break;
            }

            foreach ($activeOperations as $activeOperation) {
                $meta = $activeOperation->getMetadata();
                $this->setSocketOperationTime($activeOperation, self::META_LAST_IO_START_TIME);
                $selector->addSocketOperation($activeOperation->getSocket(), $meta[self::META_OPERATION]);
            }

            try {
                $timeout     = $this->calculateSelectorTimeout($activeOperations);
                $context     = $selector->select($timeout['sec'], $timeout['microsec']);
                $doneSockets = array_merge(
                    $this->processSingleIoEvent($context->getRead(), EventType::READ),
                    $this->processSingleIoEvent($context->getWrite(), EventType::WRITE)
                );

                $this->disconnectSockets($doneSockets, $selector);

                $activeOperations = array_diff_key($activeOperations, $doneSockets);
            } catch (TimeoutException $e) {
                // do nothing
            } catch (SocketException $e) {
                foreach ($this->socketBag->getItems() as $item) {
                    $this->callExceptionSubscribers($item->getSocket(), $e, null);
                }

                return;
            }

            $timeoutOperations = $this->processTimeoutSockets($activeOperations);

            $this->disconnectSockets($timeoutOperations, $selector);

            unset($doneSockets, $timeoutOperations);
        } while (true);
    }

    /**
     * Disconnect given socket
     *
     * @param OperationMetadata $operation Operation object
     * @param AsyncSelector     $selector Selector, which processing this socket
     *
     * @return void
     */
    private function disconnectSingleSocket(OperationMetadata $operation, AsyncSelector $selector = null)
    {
        $meta = $operation->getMetadata();

        if ($meta[self::META_REQUEST_COMPLETE]) {
            return;
        }

        $operation->setMetadata(self::META_REQUEST_COMPLETE, true);

        $socket = $operation->getSocket();
        $event  = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::DISCONNECTED);

        try {
            $socket->close();
            if ($meta[ self::META_CONNECTION_FINISH_TIME ] !== null) {
                $this->callSocketSubscribers($socket, $event);
            }
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($socket, $e, $event);
        }

        if ($selector) {
            $selector->removeAllSocketOperations($socket);
        }

        $this->callSocketSubscribers(
            $socket,
            new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::FINALIZE)
        );
    }

    /**
     * Handle socket event in subclasses
     *
     * @param SocketInterface $socket
     * @param Event           $event
     *
     * @return void
     *
     * @SuppressWarnings("unused")
     */
    protected function handleSocketEvent(SocketInterface $socket, Event $event)
    {
        // empty body
    }

    /**
     * Notify handlers about given event
     *
     * @param SocketInterface $socket Socket object
     * @param Event           $event  Event object
     *
     * @return void
     * @throws StopRequestExecuteException
     */
    private function callSocketSubscribers(SocketInterface $socket, Event $event)
    {
        if ($this->eventInvocationHandler) {
            $this->eventInvocationHandler->invokeEvent($event);
        }

        $socketHandlers = $this->socketBag->requireOperation($socket)->getEventInvocationHandler();
        if ($socketHandlers) {
            $socketHandlers->invokeEvent($event);
        }

        if ($this->decider instanceof EventInvocationHandlerInterface) {
            $this->decider->invokeEvent($event);
        }

        $this->handleSocketEvent($socket, $event);

        if ($this->isRequestStopped && !$this->isRequestStopInProgress) {
            throw new StopRequestExecuteException();
        }

        if ($event->isOperationCancelled()) {
            throw new StopSocketOperationException();
        }
    }

    /**
     * Notify handlers about exception
     *
     * @param SocketInterface $socket Socket object
     * @param SocketException $exception Thrown exception
     * @param Event           $event Event object
     *
     * @return void
     */
    private function callExceptionSubscribers(SocketInterface $socket, SocketException $exception, Event $event = null)
    {
        if ($exception instanceof StopSocketOperationException) {
            return;
        }

        $meta           = $this->socketBag->requireOperation($socket)->getMetadata();
        $exceptionEvent = new SocketExceptionEvent(
            $exception,
            $this,
            $socket,
            $meta[self::META_USER_CONTEXT],
            $event
        );
        $this->callSocketSubscribers($socket, $exceptionEvent);
    }

    /**
     * Process ready to curtain I/O operation sockets
     *
     * @param SocketInterface[] $sockets   Array of sockets, ready for certain operation
     * @param string            $eventType Event name of I/O operation
     *
     * @return OperationMetadata[] Completed operations
     */
    private function processSingleIoEvent(array $sockets, $eventType)
    {
        $result = [];
        foreach ($sockets as $socket) {
            $key          = $this->socketBag->requireOperationKey($socket);
            $item         = $this->socketBag->requireOperation($socket);
            $meta         = $item->getMetadata();
            $wasConnected = $meta[ self::META_CONNECTION_FINISH_TIME ] !== null;
            $this->setSocketOperationTime($item, self::META_CONNECTION_FINISH_TIME);
            if (!$wasConnected) {
                $event = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::CONNECTED);
                try {
                    $this->callSocketSubscribers($socket, $event);
                } catch (SocketException $e) {
                    $this->callExceptionSubscribers($socket, $e, $event);
                    $result[$key] = $item;
                    continue;
                }
            }

            if ($eventType === EventType::READ) {
                $ioState = $this->processReadIo($item, $nextOperation);
            } else {
                $ioState = $this->processWriteIo($item, $nextOperation);
            }

            switch ($ioState) {
                case self::IO_STATE_DONE:
                    if ($nextOperation === null) {
                        $result[$key] = $item;
                    } else {
                        $item->setMetadata(
                            [
                                self::META_OPERATION          => $nextOperation,
                                self::META_LAST_IO_START_TIME => null,
                            ]
                        );
                    }
                    break;
                case self::IO_STATE_PARTIAL:
                    continue;
                case self::IO_STATE_EXCEPTION:
                    $result[$key] = $item;
                    break;
            }
        }

        return $result;
    }

    /**
     * Process reading operation
     *
     * @param OperationMetadata $operationMetadata Metadata
     * @param string|null       &$nextOperation Next operation to perform on socket
     *
     * @return int One of IO_STATE_* consts
     * @throws StopRequestExecuteException
     */
    private function processReadIo(OperationMetadata $operationMetadata, &$nextOperation)
    {
        $meta     = $operationMetadata->getMetadata();
        $socket   = $operationMetadata->getSocket();
        $response = $socket->read($operationMetadata->getPreviousResponse());
        if ($response instanceof ChunkSocketResponse) {
            $operationMetadata->setPreviousResponse($response);
            return self::IO_STATE_PARTIAL;
        }

        $event = new ReadEvent($this, $socket, $meta[ self::META_USER_CONTEXT ], $response);
        try {
            $this->callSocketSubscribers($socket, $event);
            $nextOperation = $event->getNextOperation();
            return self::IO_STATE_DONE;
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($socket, $e, $event);
            return self::IO_STATE_EXCEPTION;
        }
    }

    /**
     * Process write operation
     *
     * @param OperationMetadata $operationMetadata Metadata
     * @param string|null       &$nextOperation Next operation to perform on socket
     *
     * @return int One of IO_STATE_* consts
     */
    private function processWriteIo(OperationMetadata $operationMetadata, &$nextOperation)
    {
        $meta   = $operationMetadata->getMetadata();
        $socket = $operationMetadata->getSocket();
        $event  = new WriteEvent($this, $socket, $meta[ self::META_USER_CONTEXT ]);
        try {
            $this->callSocketSubscribers($socket, $event);
            if ($event->hasData()) {
                $socket->write($event->getData());
            }
            $nextOperation = $event->getNextOperation();
            return self::IO_STATE_DONE;
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($socket, $e, $event);
            return self::IO_STATE_EXCEPTION;
        }
    }

    /**
     * Check given sockets to timeout
     *
     * @param OperationMetadata[] $operations Array of operations
     *
     * @return OperationMetadata[] Array of timeout operations
     */
    private function processTimeoutSockets(array $operations)
    {
        $result = [];
        foreach ($operations as $key => $operation) {
            $meta      = $operation->getMetadata();
            $microtime = microtime(true);
            $isTimeout =
                ($meta[self::META_CONNECTION_FINISH_TIME] === null &&
                 $microtime - $meta[self::META_CONNECTION_START_TIME] > $meta[self::META_CONNECTION_TIMEOUT]) ||
                ($meta[self::META_CONNECTION_FINISH_TIME] !== null &&
                 $meta[self::META_LAST_IO_START_TIME] !== null &&
                 $microtime - $meta[self::META_LAST_IO_START_TIME] > $meta[self::META_IO_TIMEOUT]);
            if ($isTimeout) {
                $socket = $operation->getSocket();
                $event = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::TIMEOUT);
                try {
                    $this->callSocketSubscribers($socket, $event);
                } catch (SocketException $e) {
                    $this->callExceptionSubscribers($socket, $e, $event);
                }
                $result[$key] = $operation;
            }
        }

        return $result;
    }

    /**
     * Return array of keys for socket waiting for processing
     *
     * @return OperationMetadata[]
     */
    private function getActiveOperations()
    {
        $result = [];
        foreach ($this->socketBag->getItems() as $key => $item) {
            $meta = $item->getMetadata();
            if (!$meta[self::META_REQUEST_COMPLETE] && $item->isRunning()) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Set start or finish time in metadata of the socket
     *
     * @param OperationMetadata $operationMetadata Socket meta data
     * @param string            $key Metadata key to set
     *
     * @return void
     */
    private function setSocketOperationTime(OperationMetadata $operationMetadata, $key)
    {
        $meta = $operationMetadata->getMetadata();
        switch ($key) {
            case self::META_CONNECTION_START_TIME:
                $doSetValue = $meta[self::META_CONNECTION_START_TIME] === null;
                break;

            case self::META_CONNECTION_FINISH_TIME:
                $doSetValue = $meta[self::META_CONNECTION_FINISH_TIME] === null;
                break;

            case self::META_LAST_IO_START_TIME:
                $doSetValue = $meta[self::META_CONNECTION_FINISH_TIME] !== null;
                break;

            default:
                throw new \InvalidArgumentException("Unexpected key parameter {$key} passed");
        }

        if ($doSetValue) {
            $operationMetadata->setMetadata($key, microtime(true));
        }
    }

    /**
     * Calculate selector timeout according to given array of active socket keys
     *
     * @param OperationMetadata[] $activeOperations Active socket keys
     *
     * @return array { "sec": int, "microsec": int }
     */
    private function calculateSelectorTimeout(array $activeOperations)
    {
        $result    = [ 'sec' => 0, 'microsec' => 0 ];
        $timeList  = [];
        $microtime = microtime(true);
        foreach ($activeOperations as $activeOperation) {
            $meta = $activeOperation->getMetadata();
            if ($meta[self::META_CONNECTION_FINISH_TIME] === null) {
                $timeout = $meta[self::META_CONNECTION_START_TIME] === null ?
                    $meta[self::META_CONNECTION_TIMEOUT] :
                    $meta[self::META_CONNECTION_TIMEOUT] - ($microtime - $meta[self::META_CONNECTION_START_TIME]);
            } else {
                $timeout = $meta[self::META_LAST_IO_START_TIME] === null ?
                    $meta[self::META_IO_TIMEOUT] :
                    $meta[self::META_IO_TIMEOUT] - ($microtime - $meta[self::META_LAST_IO_START_TIME])
                ;
            }

            if ($timeout > 0) {
                $timeList[] = $timeout;
            }
        }

        if ($timeList) {
            $timeout = min($timeList);
            $result = [
                'sec'      => (int) floor($timeout),
                'microsec' => round((double) $timeout - floor($timeout), 6) * 1000000
            ];
        }

        return $result;
    }

    /**
     * Return stream context from meta data
     *
     * @param array $meta Socket metadata
     *
     * @return resource|null
     */
    private function getStreamContextFromMetaData($meta)
    {
        $metaStreamContext = $meta[ self::META_SOCKET_STREAM_CONTEXT ];
        if (is_resource($metaStreamContext)) {
            return $metaStreamContext;
        } elseif (is_array($metaStreamContext)) {
            return stream_context_create(
                isset($metaStreamContext[ 'options' ]) ? $metaStreamContext[ 'options' ] : [ ],
                isset($metaStreamContext[ 'params' ]) ? $metaStreamContext[ 'params' ] : [ ]
            );
        }

        return null;
    }

    /**
     * Shutdown all sockets in case of unhandled exception
     *
     * @return void
     */
    private function emergencyShutdown()
    {
        foreach ($this->socketBag->getItems() as $item) {
            try {
                $item->getSocket()->close();
            } catch (\Exception $e) {
                // nothing required
            }

            $item->setMetadata(self::META_REQUEST_COMPLETE, true);
        }
    }

    /**
     * Disconnect array of sockets by given keys
     *
     * @param OperationMetadata[] $operations Array of operations to perform disconnect
     * @param AsyncSelector       $selector Selector object
     *
     * @return void
     */
    private function disconnectSockets(array $operations, AsyncSelector $selector = null)
    {
        foreach ($operations as $operation) {
            $this->disconnectSingleSocket($operation, $selector);
        }
    }

    /** {@inheritdoc} */
    public function setLimitationDecider(LimitationDeciderInterface $decider = null)
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Can not change limitation decider during request processing');
        }

        $this->decider = $decider;
    }

    /**
     * Initializes internal data before request
     *
     * @return void
     */
    private function initializeRequest()
    {
        if (!$this->decider) {
            $this->setLimitationDecider(new NoLimitationDecider());
        }

        $this->isExecuting             = true;
        $this->isRequestStopped        = false;
        $this->isRequestStopInProgress = false;
        $this->decider->initialize($this);
    }

    /**
     * Process internal tasks after request is finished
     *
     * @return void
     */
    private function finalizeRequest()
    {
        $this->decider->finalize($this);
        $this->isExecuting             = false;
        $this->isRequestStopped        = false;
        $this->isRequestStopInProgress = false;
    }
}
