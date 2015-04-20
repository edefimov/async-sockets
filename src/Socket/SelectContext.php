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

/**
 * Class SelectContext
 */
class SelectContext
{
    /**
     * List os sockets ready to read
     *
     * @var SocketInterface[]
     */
    private $read;

    /**
     * List of sockets ready to write
     *
     * @var SocketInterface[]
     */
    private $write;

    /**
     * Constructor
     *
     * @param SocketInterface[] $read List of ready to read sockets
     * @param SocketInterface[] $write List of ready to write sockets
     */
    public function __construct(array $read, array $write)
    {
        $this->read  = $read;
        $this->write = $write;
    }

    /**
     * Get ready to read sockets
     *
     * @return SocketInterface[]
     */
    public function getRead()
    {
        return $this->read;
    }

    /**
     * Get ready to write sockets
     *
     * @return SocketInterface[]
     */
    public function getWrite()
    {
        return $this->write;
    }
}
