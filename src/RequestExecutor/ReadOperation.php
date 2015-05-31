<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\NullFrame;

/**
 * Class ReadOperation
 */
class ReadOperation implements OperationInterface
{
    /**
     * Frame object to read
     *
     * @var FrameInterface
     */
    private $frame;

    /**
     * ReadOperation constructor.
     *
     * @param FrameInterface $frame Frame to read
     */
    public function __construct(FrameInterface $frame = null)
    {
        $this->frame = $frame ?: new NullFrame();
    }


    /** {@inheritdoc} */
    public function getType()
    {
        return RequestExecutorInterface::OPERATION_READ;
    }

    /**
     * Return Frame
     *
     * @return FrameInterface
     */
    public function getFrame()
    {
        return $this->frame;
    }

    /**
     * Sets Frame
     *
     * @param FrameInterface $frame New value for Frame
     *
     * @return void
     */
    public function setFrame(FrameInterface $frame)
    {
        $this->frame = $frame;
    }
}
