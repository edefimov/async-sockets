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
 * Class WriteEvent
 */
class WriteEvent extends IoEvent
{
    /**
     * Data to send
     *
     * @var string
     */
    private $data;

    /**
     * Constructor
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     */
    public function __construct(
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        $context
    ) {
        parent::__construct($executor, $socket, $context, EventType::WRITE);
    }

    /**
     * Return Data
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets Data
     *
     * @param string $data New value for Data
     *
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Checks whether request has data
     *
     * @return bool
     */
    public function hasData()
    {
        return $this->data !== null;
    }

    /**
     * Clear send data
     *
     * @return void
     */
    public function clearData()
    {
        $this->data = null;
    }
}
