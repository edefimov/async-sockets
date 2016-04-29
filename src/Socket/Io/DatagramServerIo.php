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

use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\SocketInterface;
use AsyncSockets\Socket\UdpClientSocket;

/**
 * Class DatagramServerIo
 */
class DatagramServerIo extends AbstractServerIo
{
    /**
     * Flag whether it is local file socket
     *
     * @var bool
     */
    private $isLocalIo;

    /**
     * DatagramServerIo constructor.
     *
     * @param SocketInterface $socket Socket object
     * @param bool            $isLocal Flag, whether it is local socket
     */
    public function __construct(SocketInterface $socket, $isLocal)
    {
        parent::__construct($socket);
        $this->isLocalIo = $isLocal;
    }

    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker, Context $context, $isOutOfBand)
    {
        stream_socket_recvfrom(
            $this->socket->getStreamResource(),
            self::SOCKET_BUFFER_SIZE,
            STREAM_PEEK,
            $remoteAddress
        );

        if (!$remoteAddress && !$this->isLocalIo) {
            stream_socket_recvfrom($this->socket->getStreamResource(), self::SOCKET_BUFFER_SIZE);
            throw new AcceptException($this->socket, 'Can not accept client: failed to receive remote address.');
        }

        $reader = new DatagramClientIo($this->socket, $this->isLocalIo ? null : $remoteAddress);
        return new AcceptedFrame(
            $remoteAddress,
            new UdpClientSocket(
                $this->socket,
                $remoteAddress,
                $reader->read(new RawFramePicker(), $context, $isOutOfBand)
            )
        );
    }
}
