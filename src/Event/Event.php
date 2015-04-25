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
 * Class Event
 *
 * @api
 */
class Event extends AbstractEvent
{
    /**
     * Socket linked to this event
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * User data for this event
     *
     * @var mixed
     */
    private $context;

    /**
     * Request executor
     *
     * @var RequestExecutorInterface
     */
    private $executor;

    /**
     * This event type
     *
     * @var string
     */
    private $type;

    /**
     * Constructor
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     * @param string                   $type One of EventType::* constants
     */
    public function __construct(RequestExecutorInterface $executor, SocketInterface $socket, $context, $type)
    {
        $this->executor = $executor;
        $this->socket   = $socket;
        $this->context  = $context;
        $this->type     = $type;
    }

    /**
     * Return associated socket
     *
     * @return SocketInterface
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Return user specified context
     *
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Return RequestExecutor
     *
     * @return RequestExecutorInterface
     */
    public function getExecutor()
    {
        return $this->executor;
    }

    /**
     * Return type of this event
     *
     * @return string One of EventType::* consts
     */
    public function getType()
    {
        return $this->type;
    }
}
