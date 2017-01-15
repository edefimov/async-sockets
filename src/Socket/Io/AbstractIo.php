<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class AbstractIo
 */
abstract class AbstractIo implements IoInterface
{
    /**
     * Socket buffer size
     */
    const SOCKET_BUFFER_SIZE = 8192;

    /**
     * Amount of attempts to set data
     */
    const IO_ATTEMPTS = 10;

    /**
     * Socket
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * AbstractIo constructor.
     *
     * @param SocketInterface $socket Socket object
     */
    public function __construct(SocketInterface $socket)
    {
        $this->socket = $socket;
    }

    /**
     * Return last php error message as string
     *
     * @return string
     */
    protected function getLastPhpErrorMessage()
    {
        $lastError = error_get_last();
        if (!empty($lastError)) {
            $phpMessage = explode(':', $lastError['message'], 2);
            $phpMessage = trim(trim(end($phpMessage)), '.') . '.';
            return $phpMessage;
        }

        return '';
    }
}
