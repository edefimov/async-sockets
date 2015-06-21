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

use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Socket\Io\TcpServerIo;

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
    protected function createIoInterface($type)
    {
        switch ($type) {
            case self::SOCKET_TYPE_TCP:
                return new TcpServerIo($this);
            default:
                throw new \LogicException("Unsupported socket resource type {$type}");
        }
    }
}
