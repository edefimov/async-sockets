<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Exception;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class UnmanagedSocketException. Throws when system detects some unexpected conditions and suppose
 * that operation couldn't be completed
 */
class UnmanagedSocketException extends NetworkSocketException
{
    /**
     * Socket data will not be correctly processed by application.
     *
     * @param SocketInterface $socket Socket object caused exception
     *
     * @return UnmanagedSocketException
     */
    public static function zombieSocketDetected(SocketInterface $socket)
    {
        return new self(
            $socket,
            sprintf(
                'System has detected a zombie connection %s and closed it. '.
                'If you see this message it means that application ' .
                'has lost control on one of its connection.',
                (string) $socket
            )
        );
    }
}
