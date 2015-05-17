<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Exception\NetworkSocketException;

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
     * @param PartialSocketResponse $previousResponse Previous response, if there was one
     *
     * @return SocketResponse
     * @throws NetworkSocketException
     *
     * @api
     */
    public function read(PartialSocketResponse $previousResponse = null);

    /**
     * Write data to this socket
     *
     * @param string $data Data to send
     *
     * @return int Number of written bytes
     * @throws NetworkSocketException
     *
     * @api
     */
    public function write($data);

    /**
     * Set blocking mode for socket
     *
     * @param bool $isBlocking Blocking mode for socket
     *
     * @return void
     * @throws NetworkSocketException If set operation failed
     *
     * @api
     */
    public function setBlocking($isBlocking);
}
