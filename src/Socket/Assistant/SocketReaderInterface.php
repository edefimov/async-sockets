<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Assistant;

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\ChunkSocketResponse;
use AsyncSockets\Socket\SocketResponseInterface;

/**
 * Interface SocketReaderInterface
 */
interface SocketReaderInterface
{
    /**
     * Perform reading data from socket
     *
     * @param resource            $socket Socket resource
     * @param FramePickerInterface      $frame Frame object to read
     * @param ChunkSocketResponse $previousResponse Previous response from this socket
     *
     * @return SocketResponseInterface
     */
    public function read($socket, FramePickerInterface $frame, ChunkSocketResponse $previousResponse = null);
}
