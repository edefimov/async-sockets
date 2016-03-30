<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Event;

use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class TimeoutEvent
 */
class TimeoutEvent extends Event
{
    /**
     * Timeout during connection
     */
    const DURING_CONNECTION = 'connection';

    /**
     * Timeout during I/O operation
     */
    const DURING_IO = 'io';

    /**
     * Stage when occured timeout
     *
     * @var string
     */
    private $when;

    /**
     * Flag whether we can enable one more I/O attempt for this socket
     *
     * @var bool
     */
    private $isNextAttemptEnabled = false;

    /**
     * TimeoutEvent constructor.
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     * @param string                   $when One of DURING_* constants
     */
    public function __construct(RequestExecutorInterface $executor, SocketInterface $socket, $context, $when)
    {
        parent::__construct($executor, $socket, $context, EventType::TIMEOUT);
        $this->when = $when;
    }

    /**
     * Return stage when timeout occurred
     *
     * @return string one of DURING_* consts
     */
    public function when()
    {
        return $this->when;
    }

    /**
     * Mark operation for try-again
     *
     * @return void
     */
    public function enableOneMoreAttempt()
    {
        $this->isNextAttemptEnabled = true;
    }

    /**
     * Return true if we can try to connect / I/O once again
     *
     * @return boolean
     */
    public function isNextAttemptEnabled()
    {
        return $this->isNextAttemptEnabled;
    }
}
