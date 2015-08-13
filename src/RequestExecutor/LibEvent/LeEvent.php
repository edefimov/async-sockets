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
     * OperationMetadata
     *
     * @var OperationMetadata
     */
    private $operationMetadata;

    /**
     * Timeout for event
     *
     * @var int|null
     */
    private $timeout;

    /**
     * LeEvent constructor.
     *
     * @param LeCallbackInterface $callback Callback object
     * @param OperationMetadata   $operationMetadata Operation metadata object
     * @param int|null                 $timeout Timeout for event
     */
    public function __construct(LeCallbackInterface $callback, OperationMetadata $operationMetadata, $timeout)
    {
        $this->handle            = event_new();
        $this->callback          = $callback;
        $this->operationMetadata = $operationMetadata;
        $this->timeout           = $timeout;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->destroy();
    }

    /**
     * Return Timeout
     *
     * @return int|null
     */
    public function getTimeout()
    {
        return $this->timeout;
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
     * Return OperationMetadata
     *
     * @return OperationMetadata
     */
    public function getOperationMetadata()
    {
        return $this->operationMetadata;
    }

    /**
     * Fire event
     *
     * @param string $eventType Type of event, one of LeCallbackInterface::EVENT_* consts
     *
     * @return void
     */
    public function fire($eventType)
    {
        $this->callback->onEvent($this->operationMetadata, $eventType);
    }

    /**
     * Destroy event handle
     *
     * @return void
     */
    private function destroy()
    {
        if ($this->handle) {
            event_del($this->handle);
            event_free($this->handle);
            $this->handle = null;
        }
    }
}
