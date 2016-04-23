<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class DatagramClientIo
 */
class DatagramClientIo extends AbstractClientIo
{
    /**
     * Destination address
     *
     * @var string
     */
    protected $remoteAddress;

    /**
     * Constructor
     *
     * @param SocketInterface $socket Socket object
     * @param string|null     $remoteAddress Destination address in form scheme://host:port or null for local files io
     */
    public function __construct(SocketInterface $socket, $remoteAddress)
    {
        parent::__construct($socket, 0);
        if ($remoteAddress) {
            $components = parse_url($remoteAddress);
            $this->remoteAddress = $components['host'] . ':' . $components['port'];
        }
    }

    /** {@inheritdoc} */
    protected function readRawDataIntoPicker(FramePickerInterface $picker)
    {
        $size     = self::SOCKET_BUFFER_SIZE;
        $resource = $this->socket->getStreamResource();
        do {
            $data = stream_socket_recvfrom($resource, $size, STREAM_PEEK);
            if (strlen($data) < $size) {
                break;
            }

            $size += $size;
        } while (true);

        $data = stream_socket_recvfrom($resource, $size, 0, $actualRemoteAddress);

        return $picker->pickUpData($data, $actualRemoteAddress);
    }

    /** {@inheritdoc} */
    protected function writeRawData($data, $isOutOfBand)
    {
        $result = stream_socket_sendto($this->socket->getStreamResource(), $data, 0, $this->remoteAddress);
        $this->throwNetworkSocketExceptionIf($result < 0, 'Failed to send data.');
        return $result;
    }

    /** {@inheritdoc} */
    protected function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    /** {@inheritdoc} */
    protected function isConnected()
    {
        return true;
    }

    /** {@inheritdoc} */
    protected function canReachFrame()
    {
        // in datagram we have no second chance to receive the frame
        return false;
    }
}
