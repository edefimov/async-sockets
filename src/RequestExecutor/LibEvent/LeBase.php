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

use AsyncSockets\RequestExecutor\OperationInterface;

/**
 * Class LeBase
 */
class LeBase
{
    /**
     * Handle to libevent handle
     *
     * @var resource
     */
    private $handle;

    /**
     * Array of registered events indexed by object id
     *
     * @var LeEvent[]
     */
    private $events = [];

    /**
     * Flag, whether loop is about to terminate
     *
     * @var bool
     */
    private $isTerminating = false;

    /**
     * LeBase constructor.
     */
    public function __construct()
    {
        $this->handle = event_base_new();
        if ($this->handle === false) {
            throw new \RuntimeException('Can not initialize libevent.');
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->destroyResource();
    }

    /**
     * Destroy libevent resource if it is opened
     *
     * @return void
     */
    private function destroyResource()
    {
        foreach ($this->events as $event) {
            $this->removeEvent($event);
        }

        if ($this->handle) {
            event_base_loopexit($this->handle, 1);
            event_base_free($this->handle);
            $this->handle = null;
        }
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
     * Start event loop
     *
     * @return int
     */
    public function startLoop()
    {
        $this->isTerminating = false;
        return event_base_loop($this->handle);
    }

    /**
     * Break loop
     *
     * @return void
     */
    public function breakLoop()
    {
        $this->isTerminating = true;
        event_base_loopbreak($this->handle);
    }

    /**
     * Connect given array of sockets
     *
     * @param LeEvent $event Event object
     *
     * @return string
     */
    private function getEventKey(LeEvent $event)
    {
        return spl_object_hash($event);
    }

    /**
     * Remove event form list
     *
     * @param LeEvent $event LibEvent event object
     *
     * @return void
     */
    public function removeEvent(LeEvent $event)
    {
        $key = $this->getEventKey($event);
        if (isset($this->events[$key])) {
            unset($this->events[$key]);
        }
    }

    /**
     * Add event to list
     *
     * @param LeEvent $event LibEvent event object
     *
     * @return void
     */
    public function addEvent(LeEvent $event)
    {
        $key = $this->getEventKey($event);
        if (!isset($this->events[$key])) {
            $this->events[$key] = $event;

            $timeout = $event->getTimeout();
            $flags   = $timeout !== null ? EV_TIMEOUT : 0;

            event_set(
                $event->getHandle(),
                $event->getOperationMetadata()->getSocket()->getStreamResource(),
                $flags | $this->getEventFlags($event),
                function ($streamResource, $eventFlags, $eventKey) {
                    if (isset($this->events[$eventKey])) {
                        $event = $this->events[$eventKey];
                        $this->onEvent($event, $eventFlags);
                        $this->removeEvent($event);
                    }
                },
                $key
            );

            event_base_set($event->getHandle(), $this->handle);
            event_add($event->getHandle(), $timeout !== null ? $timeout * 1E6 : -1);
        }
    }

    /**
     * Return set of flags for listening events
     *
     * @param LeEvent $event Libevent event object
     *
     * @return int
     */
    private function getEventFlags(LeEvent $event)
    {
        $map = [
            OperationInterface::OPERATION_READ  => EV_READ,
            OperationInterface::OPERATION_WRITE => EV_WRITE,
        ];

        $operation = $event->getOperationMetadata()->getOperation()->getType();

        return isset($map[$operation]) ? $map[$operation] : 0;
    }

    /**
     * Process libevent event
     *
     * @param LeEvent $event Libevent event object
     * @param int     $eventFlags Event flag
     *
     * @return void
     */
    private function onEvent(LeEvent $event, $eventFlags)
    {
        $fireTimeout = true;
        if (!$this->isTerminating && $eventFlags & EV_READ) {
            $fireTimeout = false;
            $event->fire(LeCallbackInterface::EVENT_READ);
        }

        if (!$this->isTerminating && $eventFlags & EV_WRITE) {
            $fireTimeout = false;
            $event->fire(LeCallbackInterface::EVENT_WRITE);
        }

        if (!$this->isTerminating && $fireTimeout && ($eventFlags & EV_TIMEOUT)) {
            $event->fire(LeCallbackInterface::EVENT_TIMEOUT);
        }
    }
}
