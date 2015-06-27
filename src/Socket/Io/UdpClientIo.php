<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class UdpClientIo
 */
class UdpClientIo extends AbstractClientIo
{
    /**
     * Destination address
     *
     * @var string
     */
    private $remoteAddress;

    /**
     * Constructor
     *
     * @param SocketInterface $socket Socket object
     * @param string|null     $remoteAddress Destination address in form scheme://host:port or null for local files io
     */
    public function __construct(SocketInterface $socket, $remoteAddress)
    {
        parent::__construct($socket);
        if ($remoteAddress) {
            $components = parse_url($remoteAddress);
            $this->remoteAddress = $components['host'] . ':' . $components['port'];
        }
        $this->remoteAddress = substr($remoteAddress, 6);
    }

    /** {@inheritdoc} */
    protected function readRawData()
    {
        $size     = self::SOCKET_BUFFER_SIZE;
        $resource = $this->socket->getStreamResource();
        do {
            $data = stream_socket_recvfrom($resource, $size, MSG_PEEK, $actualRemoteAddress);
            if ($this->remoteAddress && $actualRemoteAddress !== $this->remoteAddress) {
                return '';
            }

            if (strlen($data) < $size) {
                break;
            }

            $size += $size;
        } while (true);

        return stream_socket_recvfrom($resource, $size, 0);
    }

    /** {@inheritdoc} */
    protected function writeRawData($data)
    {
        return stream_socket_sendto($this->socket->getStreamResource(), $data, 0, $this->remoteAddress);
    }

    /** {@inheritdoc} */
    protected function isConnected()
    {
        return true;
    }
}
