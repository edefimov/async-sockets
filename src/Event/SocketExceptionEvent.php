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
     * Constructor
     *
     * @param SocketException          $exception Exception occurred during request
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     */
    public function __construct(
        SocketException          $exception,
        RequestExecutorInterface $executor,
        SocketInterface          $socket,
        $context
    ) {
        parent::__construct($executor, $socket, $context, EventType::EXCEPTION);
        $this->exception = $exception;
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
}
