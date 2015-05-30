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
use AsyncSockets\Socket\ChunkSocketResponse;
use AsyncSockets\Socket\SocketInterface;
use AsyncSockets\Socket\SocketResponse;
use AsyncSockets\Socket\SocketResponseInterface;

/**
 * Class ReadEvent
 */
class ReadEvent extends IoEvent
{
    /**
     * Data read from network
     *
     * @var SocketResponseInterface
     */
    private $response;

    /**
     * Constructor
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     * @param SocketResponseInterface  $response Network data for read operation
     */
    public function __construct(
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        $context,
        SocketResponseInterface $response
    ) {
        parent::__construct($executor, $socket, $context, EventType::READ);
        $this->response = $response;
    }

    /**
     * Return Response
     *
     * @return SocketResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Return true, if response in this event is partial
     *
     * @return bool
     */
    public function isPartial()
    {
        return $this->response instanceof ChunkSocketResponse;
    }
}
