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
            $event->unregister();
        }

        if ($this->handle) {
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
     * Check whether loop is terminating
     *
     * @return boolean
     */
    public function isTerminating()
    {
        return $this->isTerminating;
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
        }
    }
}
