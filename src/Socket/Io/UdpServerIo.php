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

use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
use AsyncSockets\Socket\UdpClientSocket;

/**
 * Class UdpServerIo
 */
class UdpServerIo extends AbstractIo
{
    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker)
    {
        $data = stream_socket_recvfrom(
            $this->socket->getStreamResource(),
            self::SOCKET_BUFFER_SIZE,
            MSG_PEEK,
            $remoteAddress
        );

        if (!$remoteAddress) {
            if ($data) {
                stream_socket_recvfrom(
                    $this->socket->getStreamResource(),
                    self::SOCKET_BUFFER_SIZE
                );
            }
            throw new AcceptException($this->socket, 'Can not accept client.');
        }

        $reader = new UdpClientIo($this->socket, $remoteAddress);
        return new AcceptedFrame(
            $remoteAddress,
            new UdpClientSocket(
                $this->socket,
                $remoteAddress,
                $reader->read(new NullFramePicker())
            )
        );
    }

    /** {@inheritdoc} */
    public function write($data)
    {
        throw new NetworkSocketException($this->socket, 'Can not write data to tcp server socket.');
    }
}
