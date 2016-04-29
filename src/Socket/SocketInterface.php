<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Interface SocketInterface
 *
 * @api
 */
interface SocketInterface extends StreamResourceInterface
{
    /**
     * Tries to connect to server
     *
     * @param string        $address Network address to open in form transport://path:port
     * @param resource|null $context Valid stream context created by function stream_context_create or null
     *
     * @return bool
     * @throws NetworkSocketException
     *
     * @api
     */
    public function open($address, $context = null);

    /**
     * Close connection to socket
     *
     * @return void
     *
     * @api
     */
    public function close();

    /**
     * Read data from this socket
     *
     * @param FramePickerInterface $picker Frame data picker, if null then read data until transfer is not complete
     * @param bool                 $isOutOfBand Flag if operation is out of band
     *
     * @return FrameInterface
     * @throws NetworkSocketException
     *
     * @api
     */
    public function read(FramePickerInterface $picker, $isOutOfBand = false);

    /**
     * Write data to this socket
     *
     * @param string $data Data to send
     * @param bool   $isOutOfBand Flag if operation is out of band
     *
     * @return int Number of written bytes
     * @api
     */
    public function write($data, $isOutOfBand = false);

    /**
     * Return debug information about this socket
     *
     * @return string
     */
    public function __toString();
}
