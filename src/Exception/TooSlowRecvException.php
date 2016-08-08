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
 * Class TooSlowRecvException. Thrown when transfer speed is slow then given in socket setup
 */
class TooSlowRecvException extends RecvDataException
{
    /**
     * Speed at the moment of exception in bytes per second
     *
     * @var double
     */
    private $speed;

    /**
     * Duration of low speed in seconds
     *
     * @var int
     */
    private $duration;

    /**
     * {@inheritDoc}
     */
    public function __construct(
        SocketInterface $socket,
        $speed,
        $duration,
        $message = '',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($socket, $message, $code, $previous);
        $this->speed    = $speed;
        $this->duration = $duration;
    }

    /**
     * tooSlowDataReceiving
     *
     * @param SocketInterface $socket Socket
     * @param double          $speed Speed at the moment of exception in bytes per second
     * @param int             $duration Duration of low speed in seconds
     *
     * @return TooSlowRecvException
     */
    public static function tooSlowDataReceiving(SocketInterface $socket, $speed, $duration)
    {
        return new self(
            $socket,
            $speed,
            $duration,
            'Data transfer is going to be aborted because of too slow speed.'
        );
    }

    /**
     * Return speed at the moment of exception in bytes per second
     *
     * @return float
     */
    public function getSpeed()
    {
        return $this->speed;
    }

    /**
     * Return duration of low speed in seconds
     *
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }
}
