<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Socket\Io\DatagramClientIo;
use AsyncSockets\Socket\Io\StreamedClientIo;

/**
 * Class AbstractClientSocket
 */
abstract class AbstractClientSocket extends AbstractSocket
{
    /** {@inheritdoc} */
    protected function createIoInterface($type, $address)
    {
        switch ($type) {
            case self::SOCKET_TYPE_UNIX:
                return new StreamedClientIo($this, 0);
            case self::SOCKET_TYPE_TCP:
                return new StreamedClientIo($this, 1);
            case self::SOCKET_TYPE_UDG:
                return new DatagramClientIo($this, null);
            case self::SOCKET_TYPE_UDP:
                return new DatagramClientIo($this, $address);
            default:
                throw new \LogicException("Unsupported socket resource type {$type}");
        }
    }
}
