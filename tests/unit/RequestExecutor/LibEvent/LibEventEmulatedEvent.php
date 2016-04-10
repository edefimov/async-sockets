<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\RequestExecutor\LibEvent;

/**
 * Class LibEventEmulatedEvent
 */
class LibEventEmulatedEvent
{
    /**
     * Libevent event flags
     *
     * @var int
     */
    private $eventFlags;

    /**
     * Event argument
     *
     * @var mixed
     */
    private $eventArg;

    /**
     * LibEventEmulatedEvent constructor.
     *
     * @param int   $eventFlags LibEvent event flags
     * @param mixed $eventArg Event argument
     */
    public function __construct($eventFlags, $eventArg)
    {
        $this->eventFlags = $eventFlags;
        $this->eventArg   = $eventArg;
    }

    /**
     * Get event flags
     *
     * @return int
     */
    public function getEventFlags()
    {
        return $this->eventFlags;
    }

    /**
     * Sets EventFlags
     *
     * @param int $eventFlags New value for EventFlags
     *
     * @return void
     */
    public function setEventFlags($eventFlags)
    {
        $this->eventFlags = $eventFlags;
    }

    /**
     * Return event argument
     *
     * @return mixed
     */
    public function getEventArg()
    {
        return $this->eventArg;
    }
}
