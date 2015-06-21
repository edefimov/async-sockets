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
use AsyncSockets\Socket\AcceptedSocket;

/**
 * Class TcpServerIo
 */
class TcpServerIo extends AbstractIo
{
    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker)
    {
        $client = stream_socket_accept($this->socket->getStreamResource(), 0, $peerName);
        if ($client === false) {
            throw new AcceptException($this->socket, 'Can not accept client connection.');
        }

        return new AcceptedFrame(
            $peerName ?: '',
            new AcceptedSocket($client)
        );
    }

    /** {@inheritdoc} */
    public function write($data)
    {
        throw new NetworkSocketException($this->socket, 'Can not write data to tcp server socket.');
    }
}
