<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Exception;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class NetworkSocketException.
 * This exception can be thrown during network operations.
 */
class NetworkSocketException extends SocketException
{
    /**
     * Socket with this exception
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * Construct the exception.
     *
     * @param SocketInterface $socket   Socket object
     * @param string          $message  The Exception message to throw.
     * @param int             $code     The Exception code.
     * @param \Exception      $previous The previous exception used for the exception chaining.
     */
    public function __construct(SocketInterface $socket, $message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->socket = $socket;
    }

    /**
     * Return socket with this exception
     *
     * @return SocketInterface
     */
    public function getSocket()
    {
        return $this->socket;
    }
}
