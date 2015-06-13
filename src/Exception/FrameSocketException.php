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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class FrameSocketException
 */
class FrameSocketException extends NetworkSocketException
{
    /**
     * Failed framePicker
     *
     * @var FramePickerInterface
     */
    private $frame;

    /**
     * Construct the exception.
     *
     * @param FramePickerInterface  $frame Corrupted framePicker
     * @param SocketInterface $socket Socket object
     * @param string          $message The Exception message to throw.
     * @param int             $code The Exception code.
     * @param \Exception      $previous The previous exception used for the exception chaining.
     */
    public function __construct(
        FramePickerInterface $frame,
        SocketInterface $socket,
        $message = '',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($socket, $message, $code, $previous);
        $this->frame = $frame;
    }

    /**
     * Return corrupted framePicker
     *
     * @return FramePickerInterface
     */
    public function getFrame()
    {
        return $this->frame;
    }
}
