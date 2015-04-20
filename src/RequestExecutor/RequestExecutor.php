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
use AsyncSockets\Exception\TimeoutException;
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
     * @var SocketInterface[][]
     */
    private $sockets = [];

    /**
     * List of registered callables for this executor
     *
     * @var callable[]
     */
    private $subscribers = [];

    /**
     * Flag whether we are executing request
     *
     * @var bool
     */
    private $isExecuting = false;


    /** {@inheritdoc} */
    public function addSocket(SocketInterface $socket, $operation, array $metadata = null)
    {
        $hash = $this->getSocketStorageKey($socket);
        if (isset($this->sockets[$hash])) {
            throw new \LogicException('Can not add socket twice');
        }

        $meta = array_merge(
            [
                self::META_ADDRESS               => null,
                self::META_USER_CONTEXT          => null,
                self::META_SOCKET_STREAM_CONTEXT => null,
                self::META_CONNECTION_TIMEOUT    => (int) ini_get('default_socket_timeout'),
                self::META_IO_TIMEOUT            => (double) ini_get('default_socket_timeout'),
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

        $this->sockets[$hash] = [
            'socket'      => $socket,
            'subscribers' => null,
            'meta'        => $meta,
        ];
    }

    /** {@inheritdoc} */
    public function removeSocket(SocketInterface $socket)
    {
        $key = $this->getSocketStorageKey($socket);
        if (!isset($this->sockets[$key])) {
            return;
        }

        $meta = $this->sockets[$key]['meta'];
        if (!$meta[self::META_REQUEST_COMPLETE] && $this->isExecuting()) {
            throw new \LogicException('Can not remove unprocessed socket during request processing');
        }

        unset($this->sockets[$key]);
    }


    /** {@inheritdoc} */
    public function getSocketMetaData(SocketInterface $socket)
    {
        $hash = $this->requireAddedSocketKey($socket);
        return $this->sockets[$hash]['meta'];
    }

    /** {@inheritdoc} */
    public function setSocketMetaData(SocketInterface $socket, $key, $value = null)
    {
        $writeableKeys = [
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

        $key  = array_intersect_key($key, $writeableKeys);
        $hash = $this->requireAddedSocketKey($socket);

        $this->sockets[$hash]['meta'] = array_merge(
            $this->sockets[$hash]['meta'],
            $key
        );
    }

    /** {@inheritdoc} */
    public function subscribe(array $events, SocketInterface $socket = null)
    {
        if ($socket) {
            $hash = $this->requireAddedSocketKey($socket);
            if (!$this->sockets[$hash]['subscribers']) {
                $this->sockets[$hash]['subscribers'] = [];
            }

            $eventStorage = & $this->sockets[$hash]['subscribers'];
        } else {
            $eventStorage = & $this->subscribers;
        }

        foreach ($events as $eventName => $subscriber) {
            $eventStorage[$eventName] = array_merge(
                isset($eventStorage[$eventName]) ? $eventStorage[$eventName] : [],
                is_callable($subscriber) ? [$subscriber] : $subscriber
            );
        }
    }


    /** {@inheritdoc} */
    public function isExecuting()
    {
        return $this->isExecuting;
    }

    /** {@inheritdoc} */
    public function execute()
    {
        if ($this->isExecuting()) {
            throw new \LogicException('Request is already in progress');
        }
        $this->isExecuting = true;

        $this->processMainExecutionLoop();
        foreach ($this->sockets as $item) {
            $this->disconnectSingleSocket($item['socket']);
        }
        $this->isExecuting = false;
    }

    /**
     * Verifies that socket was added and return its key in storage
     *
     * @param SocketInterface $socket Socket object
     *
     * @return string
     */
    private function requireAddedSocketKey(SocketInterface $socket)
    {
        $hash = $this->getSocketStorageKey($socket);
        if (!isset($this->sockets[$hash])) {
            throw new \OutOfBoundsException('Trying to perform operation on not added socket');
        }

        return $hash;
    }

    /**
     * Process connect phase
     *
     * @return void
     */
    private function processConnect()
    {
        foreach ($this->sockets as $hash => $item) {
            $meta = $item['meta'];
            if ($meta[self::META_CONNECTION_START_TIME] !== null || $meta[self::META_REQUEST_COMPLETE]) {
                continue;
            }

            $socket = $item['socket'];
            $event  = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::OPEN);

            try {
                $this->setSocketOperationTime($this->sockets[$hash]['meta'], self::META_CONNECTION_START_TIME);
                $this->callSocketSubscribers($socket, $event);
                if (!$socket->getStreamResource()) {
                    $meta = $this->sockets[$hash]['meta'];
                    $streamContext     = null;
                    $metaStreamContext = $meta[ self::META_SOCKET_STREAM_CONTEXT ];
                    if (is_resource($metaStreamContext)) {
                        $streamContext = $metaStreamContext;
                    } elseif (is_array($metaStreamContext)) {
                        $streamContext = stream_context_create(
                            isset($metaStreamContext['options']) ? $metaStreamContext['options'] : [],
                            isset($metaStreamContext['params']) ? $metaStreamContext['params'] : []
                        );
                    }

                    $socket->open($meta[self::META_ADDRESS], $streamContext);
                }

            } catch (\Exception $e) {
                $this->sockets[$hash]['meta'][self::META_REQUEST_COMPLETE] = true;
                $this->callExceptionSubscribers($socket, $event, $e);
            }
        }
    }

    /**
     * Process I/O operations on sockets
     *
     * @return void
     */
    private function processMainExecutionLoop()
    {
        $selector = new AsyncSelector();

        try {
            do {
                $this->processConnect();
                $activeSocketsKeys = $this->getActiveSocketKeys();
                if (!$activeSocketsKeys) {
                    break;
                }

                foreach ($activeSocketsKeys as $key) {
                    $item = $this->sockets[$key];
                    $meta = $item['meta'];
                    $this->setSocketOperationTime($this->sockets[$key]['meta'], self::META_LAST_IO_START_TIME);
                    $selector->addSocketOperation($item['socket'], $meta[self::META_OPERATION]);
                }

                try {
                    $timeout     = $this->calculateSelectorTimeout($activeSocketsKeys);
                    $context     = $selector->select($timeout['sec'], $timeout['microsec']);
                    $doneSockets = array_merge(
                        $this->processSingleIoEvent($context->getRead(), EventType::READ),
                        $this->processSingleIoEvent($context->getWrite(), EventType::WRITE)
                    );

                    foreach ($doneSockets as $key) {
                        $this->disconnectSingleSocket($this->sockets[$key]['socket'], $selector);
                    }

                    $activeSocketsKeys = array_diff($activeSocketsKeys, $doneSockets);
                } catch (TimeoutException $e) {
                    // do nothing
                }

                $timeoutKeys = $this->processTimeoutSockets($activeSocketsKeys);

                foreach ($timeoutKeys as $key) {
                    $this->disconnectSingleSocket($this->sockets[$key]['socket'], $selector);
                }

                unset($doneSockets, $timeoutKeys);
            } while (true);
        } catch (\Exception $e) {
            foreach ($this->sockets as $item) {
                $meta  = $item['meta'];
                $event = new Event($this, $item['socket'], $meta[self::META_USER_CONTEXT], 'internal');
                $this->callExceptionSubscribers($item['socket'], $event, $e);
            }
        }
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
        $hash = $this->requireAddedSocketKey($socket);
        $item = $this->sockets[$hash];
        $meta = $item['meta'];

        if ($meta[self::META_REQUEST_COMPLETE]) {
            return;
        }

        $this->sockets[$hash]['meta'][self::META_REQUEST_COMPLETE] = true;

        $socket = $item['socket'];
        $event  = new Event($this, $socket, $meta[self::META_USER_CONTEXT], EventType::DISCONNECTED);

        try {
            $socket->close();
            $this->callSocketSubscribers($socket, $event);
        } catch (\Exception $e) {
            $this->callExceptionSubscribers($socket, $event, $e);
        }

        if ($selector) {
            $selector->removeAllSocketOperations($socket);
        }
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
        $hash              = $this->requireAddedSocketKey($socket);
        $socketInfo        = $this->sockets[ $hash ];
        $subscribers       = $socketInfo['subscribers'] ?: [ ];
        $subscribers       = isset($subscribers[ $eventName ]) ? $subscribers[ $eventName ] : [ ];
        $globalSubscribers = isset($this->subscribers[ $eventName ]) ? $this->subscribers[ $eventName ] : [ ];

        return array_merge($subscribers, $globalSubscribers);
    }

    /**
     * Notify subscribers about given event
     *
     * @param SocketInterface $socket Socket object
     * @param Event           $event  Event object
     *
     * @return void
     */
    private function callSocketSubscribers(SocketInterface $socket, Event $event)
    {
        $subscribers = $this->getEventSubscribers($socket, $event->getType());
        foreach ($subscribers as $subscriber) {
            call_user_func_array($subscriber, [$event]);
        }
    }

    /**
     * Notify subscribers about exception
     *
     * @param SocketInterface $socket Socket object
     * @param Event           $event Event object
     * @param \Exception      $exception Thrown exception
     *
     * @return void
     */
    private function callExceptionSubscribers(SocketInterface $socket, Event $event, \Exception $exception)
    {
        $exceptionEvent = new SocketExceptionEvent($exception, $event, $this, $socket, $event->getContext());
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
            $key   = $this->requireAddedSocketKey($socket);
            $meta  = & $this->sockets[$key]['meta'];
            $event = new IoEvent($this, $socket, $meta[self::META_USER_CONTEXT], $eventType);
            $this->setSocketOperationTime($meta, self::META_CONNECTION_FINISH_TIME);

            try {
                $this->callSocketSubscribers($socket, $event);
                $nextOperation = $event->getNextOperation();
                if ($nextOperation === null) {
                    $result[] = $key;
                } else {
                    $meta[self::META_OPERATION] = $nextOperation;
                }
            } catch (\Exception $e) {
                $this->callExceptionSubscribers($socket, $event, $e);
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
            $item      = $this->sockets[$key];
            $meta      = & $this->sockets[$key]['meta'];
            $microtime = microtime(true);
            $isTimeout =
                ($meta[self::META_CONNECTION_FINISH_TIME] === null &&
                 $microtime - $meta[self::META_CONNECTION_START_TIME] > $meta[self::META_CONNECTION_TIMEOUT]) ||
                ($meta[self::META_CONNECTION_FINISH_TIME] !== null &&
                 $microtime - $meta[self::META_LAST_IO_START_TIME] > $meta[self::META_IO_TIMEOUT]);
            if ($isTimeout) {
                $event = new Event($this, $item['socket'], $meta[self::META_USER_CONTEXT], EventType::TIMEOUT);
                try {
                    $this->callSocketSubscribers($item['socket'], $event);
                } catch (\Exception $e) {
                    $this->callExceptionSubscribers($item['socket'], $event, $e);
                }
                $result[] = $key;
            }
            unset($meta);
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
        foreach ($this->sockets as $key => $item) {
            if (!$item['meta'][self::META_REQUEST_COMPLETE]) {
                $result[ ] = $key;
            }
        }

        return $result;
    }

    /**
     * Set start or finish time in metadata of the socket
     *
     * @param array &$meta Socket meta data
     * @param string $key Metadata key to set
     *
     * @return void
     */
    private function setSocketOperationTime(array &$meta, $key)
    {
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
            $meta[$key] = microtime(true);
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
        if (!$activeKeys) {
            return [ 'sec' => 0, 'microsec' => 0 ];
        }

        $timeList = [];
        foreach ($activeKeys as $key) {
            $meta = $this->sockets[$key]['meta'];
            if ($meta[self::META_CONNECTION_FINISH_TIME] === null) {
                $timeList[] = (double) $meta[self::META_CONNECTION_TIMEOUT];
            } else {
                $timeList[] = (double) $meta[self::META_IO_TIMEOUT];
            }
        }

        $tm = min($timeList);
        return [
            'sec'      => (int) floor($tm),
            'microsec' => round((double) $tm - floor($tm), 6) * 1000000
        ];
    }

    /**
     * Return socket key in internal storage
     *
     * @param SocketInterface $socket Socket object
     *
     * @return string
     */
    private function getSocketStorageKey(SocketInterface $socket)
    {
        return spl_object_hash($socket);
    }
}
