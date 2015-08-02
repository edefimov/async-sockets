<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\LibEvent;

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\OperationInterface;

/**
 * Class LeEvent
 */
class LeEvent
{
    /**
     * Libevent event handle
     *
     * @var resource
     */
    private $handle;

    /**
     * Callback object
     *
     * @var LeCallbackInterface
     */
    private $callback;

    /**
     * LeBase
     *
     * @var LeBase
     */
    private $base;

    /**
     * LeEvent constructor.
     *
     * @param LeBase              $base Lib event base
     * @param LeCallbackInterface $callback Callback object
     */
    public function __construct(LeBase $base, LeCallbackInterface $callback)
    {
        $this->base     = $base;
        $this->callback = $callback;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->unregister();
    }

    /**
     * Return Handle
     *
     * @return resource
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Register event in libevent
     *
     * @param OperationMetadata $operationMetadata Socket operation object
     * @param int|null          $timeout Timeout in seconds
     *
     */
    public function register(OperationMetadata $operationMetadata, $timeout)
    {
        $this->base->addEvent($this);
        $this->setupEvent($operationMetadata, $timeout);
    }

    /**
     * Remove this event from libevent base
     *
     * @return void
     */
    public function unregister()
    {
        $this->base->removeEvent($this);
        if ($this->handle) {
            event_del($this->handle);
            event_free($this->handle);
            $this->handle = null;
        }
    }

    /**
     * Setup libevent for given operation
     *
     * @param OperationMetadata $operationMetadata Socket operation object
     * @param int|null          $timeout Timeout in seconds
     *
     */
    private function setupEvent(OperationMetadata $operationMetadata, $timeout)
    {
        $this->handle = event_new();

        $flags = $timeout !== null ? EV_TIMEOUT : 0;
        event_set(
            $this->handle,
            $operationMetadata->getSocket()->getStreamResource(),
            $flags | $this->getEventFlags($operationMetadata),
            function ($streamResource, $eventFlags, OperationMetadata $operationMetadata) {
                $this->onEvent($eventFlags, $operationMetadata);
            },
            $operationMetadata
        );


        event_base_set($this->handle, $this->base->getHandle());
        event_add($this->handle, $timeout !== null ? $timeout * 1E6 : -1);
    }

    /**
     * Return set of flags for listening events
     *
     * @param OperationMetadata $operationMetadata
     *
     * @return int
     */
    private function getEventFlags(OperationMetadata $operationMetadata)
    {
        $map = [
            OperationInterface::OPERATION_READ  => EV_READ,
            OperationInterface::OPERATION_WRITE => EV_WRITE,
        ];

        $operation = $operationMetadata->getOperation()->getType();

        return isset($map[$operation]) ? $map[$operation] : 0;
    }

    /**
     * Process libevent event
     *
     * @param int               $eventFlags Event flag
     * @param OperationMetadata $operationMetadata
     *
     * @return void
     */
    private function onEvent($eventFlags, OperationMetadata $operationMetadata)
    {
        $fireTimeout = true;
        if (!$this->base->isTerminating() && $eventFlags & EV_READ) {
            $fireTimeout = false;
            $this->callback->onEvent($operationMetadata, LeCallbackInterface::EVENT_READ);
        }

        if (!$this->base->isTerminating() && $eventFlags & EV_WRITE) {
            $fireTimeout = false;
            $this->callback->onEvent($operationMetadata, LeCallbackInterface::EVENT_WRITE);
        }

        if (!$this->base->isTerminating() && $fireTimeout && ($eventFlags & EV_TIMEOUT)) {
            $this->callback->onEvent($operationMetadata, LeCallbackInterface::EVENT_TIMEOUT);
        }
    }
}
