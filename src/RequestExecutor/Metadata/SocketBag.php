<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\RequestExecutor\OperationInterface;
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
     * @var OperationMetadata[]
     */
    private $items;

    /**
     * Default socket timeout, seconds
     *
     * @var int
     */
    private $defaultSocketTimeout;

    /**
     * SocketBag constructor.
     *
     * @param RequestExecutorInterface $executor Owner RequestExecutor
     */
    public function __construct(RequestExecutorInterface $executor)
    {
        $this->executor             = $executor;
        $this->items                = [ ];
        $this->defaultSocketTimeout = (int) ini_get('default_socket_timeout');
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
                RequestExecutorInterface::META_ADDRESS               => null,
                RequestExecutorInterface::META_USER_CONTEXT          => null,
                RequestExecutorInterface::META_OPERATION             => null,
                RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT => null,
                RequestExecutorInterface::META_CONNECTION_TIMEOUT    => (int) $this->defaultSocketTimeout,
                RequestExecutorInterface::META_IO_TIMEOUT            => (double) $this->defaultSocketTimeout,
            ],
            $metadata ?: [],
            [
                RequestExecutorInterface::META_CONNECTION_START_TIME  => null,
                RequestExecutorInterface::META_CONNECTION_FINISH_TIME => null,
                RequestExecutorInterface::META_LAST_IO_START_TIME     => null,
                RequestExecutorInterface::META_REQUEST_COMPLETE       => false,
            ]
        );

        $this->items[$hash] = new OperationMetadata($socket, $operation, $meta, $eventHandlers);
    }

    /** {@inheritdoc} */
    public function getSocketOperation(SocketInterface $socket)
    {
        return $this->requireOperation($socket)->getOperation();
    }

    /** {@inheritdoc} */
    public function setSocketOperation(SocketInterface $socket, OperationInterface $operation)
    {
        $this->requireOperation($socket)->setOperation($operation);
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
    public function getSocketMetaData(SocketInterface $socket)
    {
        $hash = $this->requireOperationKey($socket);
        return $this->items[$hash]->getMetadata();
    }

    /** {@inheritdoc} */
    public function setSocketMetaData(SocketInterface $socket, $key, $value = null)
    {
        $writableKeys = [
            RequestExecutorInterface::META_ADDRESS               => 1,
            RequestExecutorInterface::META_USER_CONTEXT          => 1,
            RequestExecutorInterface::META_OPERATION             => 1,
            RequestExecutorInterface::META_CONNECTION_TIMEOUT    => 1,
            RequestExecutorInterface::META_IO_TIMEOUT            => 1,
            RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT => 1,
        ];

        if (!is_array($key)) {
            $key = [ $key => $value ];
        }

        $key  = array_intersect_key($key, $writableKeys);
        $hash = $this->requireOperationKey($socket);

        $this->items[$hash]->setMetadata($key);
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
     * Verifies that socket was added and return its key in storage
     *
     * @param SocketInterface $socket Socket object
     *
     * @return string
     * @throws \OutOfBoundsException
     */
    public function requireOperationKey(SocketInterface $socket)
    {
        $hash = $this->getOperationStorageKey($socket);
        if (!isset($this->items[$hash])) {
            throw new \OutOfBoundsException('Trying to perform operation on not added socket.');
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
    public function requireOperation(SocketInterface $socket)
    {
        return $this->items[ $this->requireOperationKey($socket) ];
    }

    /**
     * Return metadata items
     *
     * @return OperationMetadata[]
     */
    public function getItems()
    {
        return $this->items;
    }
}
