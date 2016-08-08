<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\SocketBagInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SocketBag
 */
class SocketBag implements SocketBagInterface
{
    /**
     * RequestExecutorInterface
     *
     * @var RequestExecutorInterface
     */
    private $executor;

    /**
     * Target metadata items
     *
     * @var RequestDescriptor[]
     */
    private $items;

    /**
     * Default connection timeout
     *
     * @var double
     */
    private $connectTimeout;

    /**
     * Default I/O timeout
     *
     * @var double
     */
    private $ioTimeout;

    /**
     * SocketBag constructor.
     *
     * @param RequestExecutorInterface $executor Owner RequestExecutor
     * @param double                   $connectTimeout Default connection timeout
     * @param double                   $ioTimeout Default I/O timeout
     */
    public function __construct(RequestExecutorInterface $executor, $connectTimeout, $ioTimeout)
    {
        $this->executor       = $executor;
        $this->items          = [ ];
        $this->connectTimeout = $connectTimeout;
        $this->ioTimeout      = $ioTimeout;
    }

    /** {@inheritdoc} */
    public function count()
    {
        return count($this->items);
    }


    /** {@inheritdoc} */
    public function addSocket(
        SocketInterface $socket,
        OperationInterface $operation,
        array $metadata = null,
        EventHandlerInterface $eventHandlers = null
    ) {
        $hash = $this->getOperationStorageKey($socket);
        if (isset($this->items[$hash])) {
            throw new \LogicException('Can not add socket twice.');
        }

        $meta = array_merge(
            [
                RequestExecutorInterface::META_ADDRESS                    => null,
                RequestExecutorInterface::META_USER_CONTEXT               => null,
                RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT      => null,
                RequestExecutorInterface::META_MIN_RECEIVE_SPEED          => null,
                RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION => null,
                RequestExecutorInterface::META_CONNECTION_TIMEOUT         => $this->connectTimeout,
                RequestExecutorInterface::META_IO_TIMEOUT                 => $this->ioTimeout,
            ],
            $metadata ?: [],
            [
                RequestExecutorInterface::META_CONNECTION_START_TIME  => null,
                RequestExecutorInterface::META_CONNECTION_FINISH_TIME => null,
                RequestExecutorInterface::META_LAST_IO_START_TIME     => null,
                RequestExecutorInterface::META_BYTES_SENT             => 0,
                RequestExecutorInterface::META_BYTES_RECEIVED         => 0,
                RequestExecutorInterface::META_REQUEST_COMPLETE       => false,
                RequestExecutorInterface::META_RECEIVE_SPEED          => 0,
            ]
        );

        $this->items[$hash] = new RequestDescriptor($socket, $operation, $meta, $eventHandlers);
    }

    /** {@inheritdoc} */
    public function getSocketOperation(SocketInterface $socket)
    {
        return $this->requireDescriptor($socket)->getOperation();
    }

    /** {@inheritdoc} */
    public function setSocketOperation(SocketInterface $socket, OperationInterface $operation)
    {
        $this->requireDescriptor($socket)->setOperation($operation);
    }


    /** {@inheritdoc} */
    public function hasSocket(SocketInterface $socket)
    {
        $hash = $this->getOperationStorageKey($socket);
        return isset($this->items[$hash]);
    }

    /** {@inheritdoc} */
    public function removeSocket(SocketInterface $socket)
    {
        $key = $this->getOperationStorageKey($socket);
        if (!isset($this->items[$key])) {
            return;
        }

        $meta = $this->items[$key]->getMetadata();
        if (!$meta[RequestExecutorInterface::META_REQUEST_COMPLETE] && $this->executor->isExecuting()) {
            throw new \LogicException('Can not remove unprocessed socket during request processing.');
        }

        unset($this->items[$key]);
    }

    /** {@inheritdoc} */
    public function postponeSocket(SocketInterface $socket)
    {
        $key = $this->getOperationStorageKey($socket);
        if (!isset($this->items[$key])) {
            return;
        }

        $this->items[$key]->postpone();
    }

    /** {@inheritdoc} */
    public function getSocketMetaData(SocketInterface $socket)
    {
        return $this->requireDescriptor($socket)->getMetadata();
    }

    /** {@inheritdoc} */
    public function setSocketMetaData(SocketInterface $socket, $key, $value = null)
    {
        $writableKeys = [
            RequestExecutorInterface::META_ADDRESS                    => 1,
            RequestExecutorInterface::META_USER_CONTEXT               => 1,
            RequestExecutorInterface::META_CONNECTION_TIMEOUT         => 1,
            RequestExecutorInterface::META_IO_TIMEOUT                 => 1,
            RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT      => 1,
            RequestExecutorInterface::META_MIN_RECEIVE_SPEED          => 1,
            RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION => 1,
        ];

        if (!is_array($key)) {
            $key = [ $key => $value ];
        }

        $key = array_intersect_key($key, $writableKeys);
        $this->requireDescriptor($socket)->setMetadata($key);
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
     * Require operation descriptor for given socket
     *
     * @param SocketInterface $socket Socket object
     *
     * @return RequestDescriptor
     * @throws \OutOfBoundsException
     */
    private function requireDescriptor(SocketInterface $socket)
    {
        $hash = $this->getOperationStorageKey($socket);
        if (!isset($this->items[$hash])) {
            throw new \OutOfBoundsException('Trying to perform operation on not added socket.');
        }

        return $this->items[$hash];
    }

    /**
     * Return metadata items
     *
     * @return RequestDescriptor[]
     */
    public function getItems()
    {
        return $this->items;
    }
}
