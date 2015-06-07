<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket;

use AsyncSockets\Frame\NullFrame;

/**
 * Class AcceptResponse
 */
class AcceptResponse extends AbstractSocketResponse
{
    /**
     * Client address, if available
     *
     * @var string
     */
    private $clientAddress;

    /**
     * Connected client socket
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * AcceptResponse constructor.
     *
     * @param string          $clientAddress Remote address
     * @param SocketInterface $socket Remote socket
     */
    public function __construct($clientAddress, SocketInterface $socket)
    {
        parent::__construct(new NullFrame(), $clientAddress);
        $this->clientAddress = $clientAddress;
        $this->socket        = $socket;
    }


    /** {@inheritdoc} */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Return client socket
     *
     * @return SocketInterface
     */
    public function getClientSocket()
    {
        return $this->socket;
    }

    /**
     * Return ClientAddress
     *
     * @return string
     */
    public function getClientAddress()
    {
        return $this->clientAddress;
    }
}
