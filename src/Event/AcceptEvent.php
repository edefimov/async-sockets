<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Event;

use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class AcceptEvent
 */
class AcceptEvent extends Event
{
    /**
     * Client sockets
     *
     * @var SocketInterface
     */
    private $clientSocket;

    /**
     * Remote address
     *
     * @var string
     */
    private $remoteAddress;

    /**
     * Constructor
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $serverSocket Server socket for this request
     * @param mixed                    $context  Any optional user data for event
     * @param SocketInterface          $clientSocket Accepted client socket
     * @param string                   $remoteAddress Remote address
     */
    public function __construct(
        RequestExecutorInterface $executor,
        SocketInterface $serverSocket,
        $context,
        SocketInterface $clientSocket,
        $remoteAddress
    ) {
        parent::__construct($executor, $serverSocket, $context, EventType::ACCEPT);
        $this->clientSocket  = $clientSocket;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Return accepted client socket
     *
     * @return SocketInterface
     */
    public function getClientSocket()
    {
        return $this->clientSocket;
    }

    /**
     * Return remote address
     *
     * @return string
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }
}
