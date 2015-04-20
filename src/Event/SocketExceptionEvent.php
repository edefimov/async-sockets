<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Event;

use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SocketExceptionEvent
 *
 * @api
 */
class SocketExceptionEvent extends Event
{
    /**
     * Exception linked with event
     *
     * @var \Exception
     */
    private $exception;

    /**
     * Event caused the exception
     *
     * @var Event
     */
    private $originalEvent;

    /**
     * Constructor
     *
     * @param \Exception               $exception Exception occurred during request
     * @param Event                    $originalEvent Original event
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     */
    public function __construct(
        \Exception               $exception,
        Event                    $originalEvent,
        RequestExecutorInterface $executor,
        SocketInterface          $socket,
        $context
    ) {
        parent::__construct($executor, $socket, $context, EventType::EXCEPTION);
        $this->exception     = $exception;
        $this->originalEvent = $originalEvent;
    }

    /**
     * Return thrown exception
     *
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Return event caused exception
     *
     * @return Event
     */
    public function getOriginalEvent()
    {
        return $this->originalEvent;
    }
}
