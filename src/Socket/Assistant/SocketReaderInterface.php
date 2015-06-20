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

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Interface SocketReaderInterface
 */
interface SocketReaderInterface
{
    /**
     * Perform reading data from socket
     *
     * @param resource             $socket Socket resource
     * @param FramePickerInterface $picker Frame object to read
     *
     * @return FrameInterface
     */
    public function read($socket, FramePickerInterface $picker);
}
