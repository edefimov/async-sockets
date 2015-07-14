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

use AsyncSockets\Exception\NetworkSocketException;
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
     * Delay for select operation, microseconds
     */
    const SELECT_DELAY = 50000;

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
     * Throw network operation exception
     *
     * @param bool   $condition Condition, which must evaluates to true for throwing exception
     * @param string $message Exception message
     *
     * @return void
     * @throws NetworkSocketException
     */
    protected function throwNetworkSocketExceptionIf($condition, $message)
    {
        if ($condition) {
            throw new NetworkSocketException($this->socket, $message);
        }
    }
}
