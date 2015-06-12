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
     * @var StreamResourceInterface[]
     */
    private $read;

    /**
     * List of sockets ready to write
     *
     * @var StreamResourceInterface[]
     */
    private $write;

    /**
     * Constructor
     *
     * @param StreamResourceInterface[] $read List of ready to read sockets
     * @param StreamResourceInterface[] $write List of ready to write sockets
     */
    public function __construct(array $read, array $write)
    {
        $this->read  = $read;
        $this->write = $write;
    }

    /**
     * Get ready to read sockets
     *
     * @return StreamResourceInterface[]
     */
    public function getRead()
    {
        return $this->read;
    }

    /**
     * Get ready to write sockets
     *
     * @return StreamResourceInterface[]
     */
    public function getWrite()
    {
        return $this->write;
    }
}
