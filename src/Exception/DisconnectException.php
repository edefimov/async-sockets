<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Exception;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class DisconnectException. Socket has been unexpectedly disconnected
 */
class DisconnectException extends ConnectionException
{
    /**
     * Creates lost remote connection exception
     *
     * @param SocketInterface $socket
     *
     * @return DisconnectException
     */
    public static function lostRemoteConnection(SocketInterface $socket)
    {
        return new self($socket, 'Remote connection has been lost.');
    }
}
