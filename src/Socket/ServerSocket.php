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

use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class ServerSocket
 */
class ServerSocket extends AbstractSocket
{
    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        $resource = stream_socket_server(
            $address,
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if ($errno || $resource === false) {
            throw new NetworkSocketException($this, $errstr, $errno);
        }

        return $resource;
    }

    /** {@inheritdoc} */
    protected function doReadData($socket, FramePickerInterface $picker)
    {
        $client = stream_socket_accept($socket, 0, $peerName);
        if ($client === false) {
            throw new AcceptException($this, 'Can not accept client connection.');
        }

        return new AcceptedFrame(
            $peerName ?: '',
            new AcceptedSocket($client)
        );
    }

    /** {@inheritdoc} */
    protected function isConnected($socket)
    {
        // server socket is always connected
        return true;
    }
}
