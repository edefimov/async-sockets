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

use AsyncSockets\Exception\FrameSocketException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
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
    const SEND_ATTEMPTS = 10;

    /**
     * Delay for select operation, microseconds
     */
    const SELECT_DELAY = 25000;

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

    /**
     * Checks whether all framePicker data is read
     *
     * @param resource       $socket Socket resource object
     * @param FramePickerInterface $picker Frame object to check
     *
     * @return bool
     * @throws FrameSocketException If socket data is ended and framePicker eof is not reached
     */
    protected function isFullFrameRead($socket, FramePickerInterface $picker)
    {
        if ($picker->isEof() && !($picker instanceof NullFramePicker)) {
            return true;
        }

        $read     = [ $socket ];
        $nomatter = null;
        $select   = stream_select($read, $nomatter, $nomatter, 0, self::SELECT_DELAY);
        if ($select === false) {
            throw new NetworkSocketException($this->socket, 'Failed to read data.');
        }

        if ($select === 0) {
            if ($picker->isEof()) {
                return true;
            } else {
                throw new FrameSocketException($picker, $this->socket, 'Failed to receive desired picker.');
            }
        }

        return false;
    }
}
