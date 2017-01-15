<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Event;

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class ReadEvent
 */
class ReadEvent extends IoEvent
{
    /**
     * Data read from network
     *
     * @var FrameInterface
     */
    private $frame;

    /**
     * Constructor
     *
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket Socket for this request
     * @param mixed                    $context Any optional user data for event
     * @param FrameInterface           $frame Network data for read operation
     * @param bool                     $isOutOfBand Flag if data are out of band
     */
    public function __construct(
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        $context,
        FrameInterface $frame,
        $isOutOfBand = false
    ) {
        parent::__construct($executor, $socket, $context, $isOutOfBand ? EventType::OOB : EventType::READ);
        $this->frame = $frame;
    }

    /**
     * Return response frame
     *
     * @return FrameInterface
     */
    public function getFrame()
    {
        return $this->frame;
    }

    /**
     * Return true, if frame in this event is partial
     *
     * @return bool
     */
    public function isPartial()
    {
        return $this->frame instanceof PartialFrame;
    }
}
