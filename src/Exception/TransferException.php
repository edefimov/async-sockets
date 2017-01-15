<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Exception;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class TransferException.
 * This class of exceptions describes failures with network transfers.
 */
class TransferException extends NetworkSocketException
{
    /**
     * Exception with incoming data
     */
    const DIRECTION_RECV = 0;

    /**
     * Exception with outgoing data
     */
    const DIRECTION_SEND = 1;

    /**
     * Transfer direction
     *
     * @var int
     */
    private $direction;

    /**
     * @inheritDoc
     */
    public function __construct(
        SocketInterface $socket,
        $direction,
        $message = '',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($socket, $message, $code, $previous);
        $this->direction = $direction;
    }

    /**
     * Return direction of this exception
     *
     * @return int
     */
    public function getDirection()
    {
        return $this->direction;
    }
}
