<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Interface IoInterface
 */
interface IoInterface
{
    /**
     * Perform reading data from socket and fill picker object
     *
     * @param FramePickerInterface $picker Frame object to read
     *
     * @return FrameInterface
     * @throws NetworkSocketException
     */
    public function read(FramePickerInterface $picker);

    /**
     * Write data to this socket
     *
     * @param string $data Data to send
     * @param bool   $isOutOfBand Flag if it is out-of-band data
     *
     * @return int Number of written bytes
     */
    public function write($data, $isOutOfBand);
}
