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

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Interface IoInterface
 */
interface IoInterface
{
    /**
     * Socket buffer size
     */
    const SOCKET_BUFFER_SIZE = 8192;

    /**
     * Perform reading data from socket and fill picker object
     *
     * @param FramePickerInterface $picker Frame object to read
     * @param Context              $context Socket context
     * @param bool                 $isOutOfBand Flag if it is out-of-band data
     *
     * @return FrameInterface
     */
    public function read(FramePickerInterface $picker, Context $context, $isOutOfBand);

    /**
     * Write data to this socket
     *
     * @param string  $data Data to send
     * @param Context $context Socket context
     * @param bool    $isOutOfBand Flag if it is out-of-band data
     *
     * @return int Number of written bytes
     */
    public function write($data, Context $context, $isOutOfBand);
}
