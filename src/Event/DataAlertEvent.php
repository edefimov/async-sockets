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
 * Class DataAlertEvent
 */
class DataAlertEvent extends IoEvent
{
    /**
     * Total amount of attempts before closing connection
     *
     * @var int
     */
    private $totalAttempts;

    /**
     * Current attempt number, the first is 1
     *
     * @var int
     */
    private $attempt;

    /**
     * DataAlertEvent constructor
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket Socket for this request
     * @param mixed                    $context Any optional user data for event
     * @param int                      $attempt Current attempt from 1
     * @param int                      $totalAttempts Total amount of attempts
     */
    public function __construct(
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        $context,
        $attempt,
        $totalAttempts
    ) {
        parent::__construct($executor, $socket, $context, EventType::DATA_ALERT);
        $this->totalAttempts = $totalAttempts;
        $this->attempt = $attempt;
    }

    /**
     * Return TotalAttempts
     *
     * @return int
     */
    public function getTotalAttempts()
    {
        return $this->totalAttempts;
    }

    /**
     * Return Attempt
     *
     * @return int
     */
    public function getAttempt()
    {
        return $this->attempt;
    }
}
