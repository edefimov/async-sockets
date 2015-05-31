<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Exception;

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class FrameSocketException
 */
class FrameSocketException extends NetworkSocketException
{
    /**
     * Failed frame
     *
     * @var FrameInterface
     */
    private $frame;

    /**
     * Construct the exception.
     *
     * @param FrameInterface  $frame Corrupted frame
     * @param SocketInterface $socket Socket object
     * @param string          $message The Exception message to throw.
     * @param int             $code The Exception code.
     * @param \Exception      $previous The previous exception used for the exception chaining.
     */
    public function __construct(
        FrameInterface $frame,
        SocketInterface $socket,
        $message = '',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($socket, $message, $code, $previous);
        $this->frame = $frame;
    }

    /**
     * Return corrupted frame
     *
     * @return FrameInterface
     */
    public function getFrame()
    {
        return $this->frame;
    }
}
