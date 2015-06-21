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
use AsyncSockets\Socket\Io\TcpClientIo;

/**
 * Class ClientSocket
 */
class ClientSocket extends AbstractSocket
{
    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        $resource = stream_socket_client(
            $address,
            $errno,
            $errstr,
            null,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
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
                return new TcpClientIo($this);
            default:
                throw new \LogicException("Unsupported socket resource type {$type}");
        }
    }
}
