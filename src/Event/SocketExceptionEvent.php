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

use AsyncSockets\Exception\SocketException;
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
     * @var SocketException
     */
    private $exception;

    /**
     * Event caused the exception
     *
     * @var Event|null
     */
    private $originalEvent;

    /**
     * Constructor
     *
     * @param SocketException          $exception Exception occurred during request
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     * @param Event                    $originalEvent Original event
     */
    public function __construct(
        SocketException          $exception,
        RequestExecutorInterface $executor,
        SocketInterface          $socket,
        $context,
        Event                    $originalEvent = null
    ) {
        parent::__construct($executor, $socket, $context, EventType::EXCEPTION);
        $this->exception     = $exception;
        $this->originalEvent = $originalEvent;
    }

    /**
     * Return thrown exception
     *
     * @return SocketException
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Return event caused exception
     *
     * @return Event|null Null means that exception occurred outside another Event
     */
    public function getOriginalEvent()
    {
        return $this->originalEvent;
    }
}
