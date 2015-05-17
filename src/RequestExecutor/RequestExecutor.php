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
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\StopRequestExecuteException;
use AsyncSockets\Exception\StopSocketOperationException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\RequestExecutor\Metadata\HandlerBag;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class RequestExecutor
 */
class RequestExecutor implements RequestExecutorInterface
{
    /**
     * Array of registered sockets
     *
     * @var OperationMetadata[]
     */
    private $operations = [];

    /**
     * List of registered callables for this executor
     *
     * @var HandlerBag
     */
    private $subscribers;

    /**
     * Flag whether we are executing request
     *
     * @var bool
     */
    private $isExecuting = false;

    /**
     * Default socket timeout, seconds
     *
     * @var int
     */
    private $defaultSocketTimeout;

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
     * Constructor
     */
    public function __construct()
    {
        $this->defaultSocketTimeout = (int) ini_get('default_socket_timeout');
        $this->subscribers          = new HandlerBag();
    }


    /** {@inheritdoc} */
    public function addSocket(SocketInterface $socket, $operation, array $metadata = null)
    {
        $hash = $this->getOperationStorageKey($socket);
        if (isset($this->operations[$hash])) {
            throw new \LogicException('Can not add socket twice');
        }

        $meta = array_merge(
            [
                self::META_ADDRESS               => null,
                self::META_USER_CONTEXT          => null,
                self::META_SOCKET_STREAM_CONTEXT => null,
                self::META_CONNECTION_TIMEOUT    => (int) $this->defaultSocketTimeout,
                self::META_IO_TIMEOUT            => (double) $this->defaultSocketTimeout,
            ],
            $metadata ?: [],
            [
                self::META_CONNECTION_START_TIME  => null,
                self::META_CONNECTION_FINISH_TIME => null,
                self::META_LAST_IO_START_TIME     => null,
                self::META_REQUEST_COMPLETE       => false,
                self::META_OPERATION              => $operation,
            ]
        );

        $this->operations[$hash] = new OperationMetadata($socket, $meta);
    }

    /** {@inheritdoc} */
    public function hasSocket(SocketInterface $socket)
    {
        $hash = $this->getOperationStorageKey($socket);
        return isset($this->operations[$hash]);
    }

    /** {@inheritdoc} */
    public function removeSocket(SocketInterface $socket)
    {
        $key = $this->getOperationStorageKey($socket);
        if (!isset($this->operations[$key])) {
            return;
        }

        $meta = $this->operations[$key]->getMetadata();
        if (!$meta[self::META_REQUEST_COMPLETE] && $this->isExecuting()) {
            throw new \LogicException('Can not remove unprocessed socket during request processing');
        }

        unset($this->operations[$key]);
    }


    /** {@inheritdoc} */
    public function getSocketMetaData(SocketInterface $socket)
    {
        $hash = $this->requireOperationKey($socket);
        return $this->operations[$hash]->getMetadata();
    }

    /** {@inheritdoc} */
    public function setSocketMetaData(SocketInterface $socket, $key, $value = null)
    {
        $writableKeys = [
            self::META_ADDRESS               => 1,
            self::META_USER_CONTEXT          => 1,
            self::META_OPERATION             => 1,
            self::META_CONNECTION_TIMEOUT    => 1,
            self::META_IO_TIMEOUT            => 1,
            self::META_SOCKET_STREAM_CONTEXT => 1,
        ];

        if (!is_array($key)) {
            $key = [ $key => $value ];
        }

        $key  = array_intersect_key($key, $writableKeys);
        $hash = $this->requireOperationKey($socket);

        $this->operations[$hash]->setMetadata($key);
    }

    /** {@inheritdoc} */
    public function addHandler(array $events, SocketInterface $socket = null)
    {
        $bag = $socket ?
            $this->requireOperation($socket)->getSubscribers() :
            $this->subscribers;

        $bag->addHandler($events);
    }

    /** {@inheritdoc} */
    public function removeHandler(array $events, SocketInterface $socket = null)
    {
        if ($socket) {
            $hash = $this->getOperationStorageKey($socket);
            if (!isset($this->operations[$hash])) {
                return;
            }

            $bag = $this->operations[$hash]->getSubscribers();
        } else {
            $bag = $this->subscribers;
        }

        $bag->removeHandler($events);
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
            $this->disconnectSocketsByKeys(array_keys($this->operations));
            $this->finalizeRequest();
        } catch (StopRequestExecuteException $e) {
            $this->isRequestStopInProgress = true;
            $this->disconnectSocketsByKeys(array_keys($this->operations));
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

    /** {@inheritdoc} */
    public function cancelSocketRequest(SocketInterface $socket)
    {
        if (!$this->isExecuting()) {
            throw new \BadMethodCallException('Can not stop inactive request');
        }

        $this->requireOperation($socket)->setOperationCancelled(true);
    }


    /**
     * Verifies that socket was added and return its key in storage
     *
     * @param SocketInterface $socket Socket object
     *
     * @return string
     */
    private function requireOperationKey(SocketInterface $socket)
    {
        $hash = $this->getOperationStorageKey($socket);
        if (!isset($this->operations[$hash])) {
            throw new \OutOfBoundsException('Trying to perform operation on not added socket');
        }

        return $hash;
    }

    /**
     * Require operation metadata for given socket
     *
     * @param SocketInterface $socket Socket object
     *
     * @return OperationMetadata
     */
    private function requireOperation(SocketInterface $socket)
    {
        return $this->operations[ $this->requireOperationKey($socket) ];
    }

    /**
     * Process connect phase
     *
     * @return void
     */
    private function processConnect()
    {
        foreach ($this->operations as $item) {
            $decision = $this->decide($item);
            if ($decision === LimitationDeciderInterface::DECISION_PROCESS_SCHEDULED) {
                break;
            } elseif ($decision === LimitationDeciderInterface::DECISION_SKIP_CURRENT) {
                continue;
            } elseif ($decision !== LimitationDeciderInterface::DECISION_OK) {
                throw new \LogicException('Unknown decision ' . $decision . ' received');
            }

            $socket = $item->getSocket();
            $meta   = $item->getMetadata();
            $event  = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::INITIALIZE);

            try {
                $this->callSocketSubscribers($socket, $event);
                $this->setSocketOperationTime($item, self::META_CONNECTION_START_TIME);
                if (!$socket->getStreamResource()) {
                    $streamContext = $this->getStreamContextFromMetaData($meta);
                    $socket->open($meta[self::META_ADDRESS], $streamContext);
                }
                $socket->setBlocking(false);
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

        $decision = $this->decider->decide($this, $operationMetadata->getSocket(), count($this->operations));
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
        foreach ($this->operations as $item) {
            $item->initialize();
        }

        do {
            $this->processConnect();
            $activeSocketsKeys = $this->getActiveSocketKeys();
            if (!$activeSocketsKeys) {
                break;
            }

            foreach ($activeSocketsKeys as $key) {
                $item = $this->operations[$key];
                $meta = $item->getMetadata();
                $this->setSocketOperationTime($item, self::META_LAST_IO_START_TIME);
                $selector->addSocketOperation($item->getSocket(), $meta[self::META_OPERATION]);
            }

            try {
                $timeout     = $this->calculateSelectorTimeout($activeSocketsKeys);
                $context     = $selector->select($timeout['sec'], $timeout['microsec']);
                $doneSockets = array_merge(
                    $this->processSingleIoEvent($context->getRead(), EventType::READ),
                    $this->processSingleIoEvent($context->getWrite(), EventType::WRITE)
                );

                $this->disconnectSocketsByKeys($doneSockets, $selector);

                $activeSocketsKeys = array_diff($activeSocketsKeys, $doneSockets);
            } catch (TimeoutException $e) {
                // do nothing
            } catch (SocketException $e) {
                foreach ($this->operations as $item) {
                    $this->callExceptionSubscribers($item->getSocket(), $e, null);
                }

                return;
            }

            $timeoutKeys = $this->processTimeoutSockets($activeSocketsKeys);

            $this->disconnectSocketsByKeys($timeoutKeys, $selector);

            unset($doneSockets, $timeoutKeys);
        } while (true);
    }

    /**
     * Disconnect given socket
     *
     * @param SocketInterface $socket Socket object
     * @param AsyncSelector   $selector Selector, which processing this socket
     *
     * @return void
     */
    private function disconnectSingleSocket(SocketInterface $socket, AsyncSelector $selector = null)
    {
        $item = $this->requireOperation($socket);
        $meta = $item->getMetadata();

        if ($meta[self::META_REQUEST_COMPLETE]) {
            return;
        }

        $item->setMetadata(self::META_REQUEST_COMPLETE, true);

        $socket = $item->getSocket();
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
     * @param SocketInterface $socket Socket object
     * @param string          $eventName Event name, one of EventType::*
     *
     * @return callable[] List of callable for socket for event
     * @see EventType
     */
    private function getEventSubscribers(SocketInterface $socket, $eventName)
    {
        $item = $this->requireOperation($socket);
        return array_merge(
            $item->getSubscribers()->getHandlersFor($eventName),
            $this->subscribers->getHandlersFor($eventName)
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
     * Notify subscribers about given event
     *
     * @param SocketInterface $socket Socket object
     * @param Event           $event  Event object
     *
     * @return void
     * @throws StopRequestExecuteException
     */
    private function callSocketSubscribers(SocketInterface $socket, Event $event)
    {
        $subscribers = $this->getEventSubscribers($socket, $event->getType());
        foreach ($subscribers as $subscriber) {
            call_user_func_array($subscriber, [$event]);
        }
        $this->handleSocketEvent($socket, $event);

        if ($this->isRequestStopped && !$this->isRequestStopInProgress) {
            throw new StopRequestExecuteException();
        }

        $item = $this->requireOperation($socket);
        if ($item->isOperationCancelled()) {
            $item->setOperationCancelled(false);
            throw new StopSocketOperationException();
        }
    }

    /**
     * Notify subscribers about exception
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

        $meta           = $this->requireOperation($socket)->getMetadata();
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
     * @return string[] Keys in socket storage with completed sockets
     */
    private function processSingleIoEvent(array $sockets, $eventType)
    {
        $result = [];
        foreach ($sockets as $socket) {
            $key          = $this->requireOperationKey($socket);
            $item         = $this->operations[$key];
            $meta         = $item->getMetadata();
            $wasConnected = $meta[ self::META_CONNECTION_FINISH_TIME ] !== null;
            $this->setSocketOperationTime($item, self::META_CONNECTION_FINISH_TIME);
            if (!$wasConnected) {
                $event = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::CONNECTED);
                try {
                    $this->callSocketSubscribers($socket, $event);
                } catch (SocketException $e) {
                    $this->callExceptionSubscribers($socket, $e, $event);
                    $result[] = $key;
                    continue;
                }
            }

            $event = new IoEvent($this, $socket, $meta[ self::META_USER_CONTEXT ], $eventType);
            try {
                $this->callSocketSubscribers($socket, $event);
                $nextOperation = $event->getNextOperation();
                if ($nextOperation === null) {
                    $result[] = $key;
                } else {
                    $item->setMetadata(
                        [
                            self::META_OPERATION          => $nextOperation,
                            self::META_LAST_IO_START_TIME => null,
                        ]
                    );
                }
            } catch (SocketException $e) {
                $this->callExceptionSubscribers($socket, $e, $event);
                $result[] = $key;
            }

            unset($meta);
        }

        return $result;
    }

    /**
     * Check given sockets to timeout
     *
     * @param string[] $keys Array of sockets' keys in internal storage
     *
     * @return string[] List of keys with timeout
     */
    private function processTimeoutSockets(array $keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $item      = $this->operations[$key];
            $meta      = $item->getMetadata();
            $microtime = microtime(true);
            $isTimeout =
                ($meta[self::META_CONNECTION_FINISH_TIME] === null &&
                 $microtime - $meta[self::META_CONNECTION_START_TIME] > $meta[self::META_CONNECTION_TIMEOUT]) ||
                ($meta[self::META_CONNECTION_FINISH_TIME] !== null &&
                 $meta[self::META_LAST_IO_START_TIME] !== null &&
                 $microtime - $meta[self::META_LAST_IO_START_TIME] > $meta[self::META_IO_TIMEOUT]);
            if ($isTimeout) {
                $socket = $item->getSocket();
                $event = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::TIMEOUT);
                try {
                    $this->callSocketSubscribers($socket, $event);
                } catch (SocketException $e) {
                    $this->callExceptionSubscribers($socket, $e, $event);
                }
                $result[] = $key;
            }
        }

        return $result;
    }

    /**
     * Return array of keys for socket waiting for processing
     *
     * @return string[]
     */
    private function getActiveSocketKeys()
    {
        $result = [];
        foreach ($this->operations as $key => $item) {
            $meta = $item->getMetadata();
            if (!$meta[self::META_REQUEST_COMPLETE] && $item->isRunning()) {
                $result[ ] = $key;
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
     * @param string[] $activeKeys Active socket keys
     *
     * @return array { "sec": int, "microsec": int }
     */
    private function calculateSelectorTimeout(array $activeKeys)
    {
        $result    = [ 'sec' => 0, 'microsec' => 0 ];
        $timeList  = [];
        $microtime = microtime(true);
        foreach ($activeKeys as $key) {
            $meta = $this->operations[$key]->getMetadata();
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
     * Return socket key in internal storage
     *
     * @param SocketInterface $socket Socket object
     *
     * @return string
     */
    private function getOperationStorageKey(SocketInterface $socket)
    {
        return spl_object_hash($socket);
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
        foreach ($this->operations as $item) {
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
     * @param string[]      $keys List of socket keys to disconnect
     * @param AsyncSelector $selector Selector object
     *
     * @return void
     */
    private function disconnectSocketsByKeys(array $keys, AsyncSelector $selector = null)
    {
        foreach ($keys as $key) {
            $this->disconnectSingleSocket($this->operations[ $key ]->getSocket(), $selector);
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
