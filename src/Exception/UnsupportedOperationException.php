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
 * Class UnsupportedOperationException
 */
class UnsupportedOperationException extends NetworkSocketException
{
    /**
     * Throw OOB unsupported error for given socket
     *
     * @param SocketInterface $socket Socket object tried to perform operation
     *
     * @return UnsupportedOperationException
     */
    public static function oobDataUnsupported(SocketInterface $socket)
    {
        return new self(
            $socket,
            sprintf(
                'Out-of-band data are unsupported for "%s".',
                (string) $socket
            )
        );
    }

    /**
     * Throw OOB data size exceeded for given socket
     *
     * @param SocketInterface $socket Socket object tried to perform operation
     * @param int             $packetSize Size of packet in bytes
     * @param int             $size Size of data about to sent
     *
     * @return UnsupportedOperationException
     */
    public static function oobDataPackageSizeExceeded(SocketInterface $socket, $packetSize, $size)
    {
        return new self(
            $socket,
            sprintf(
                'Out-of-band data size is exceeded for socket "%s", (%s bytes allowed, %s bytes tried to sent).',
                (string) $socket,
                $packetSize,
                $size
            )
        );
    }
}
