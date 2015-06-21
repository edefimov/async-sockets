<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Frame;

/**
 * Class AbstractFramePicker
 */
abstract class AbstractFramePicker implements FramePickerInterface
{
    /**
     * Flag, whether framePicker is finished
     *
     * @var bool
     */
    private $isFinished;

    /**
     * Frame with data for this picker
     *
     * @var FrameInterface
     */
    private $frame;

    /**
     * Collected data for this frame
     *
     * @var string
     */
    private $buffer;

    /**
     * AbstractFramePicker constructor.
     */
    public function __construct()
    {
        $this->isFinished = false;
        $this->buffer     = '';
    }

    /** {@inheritdoc} */
    public function isEof()
    {
        return $this->isFinished;
    }

    /** {@inheritdoc} */
    public function pickUpData($chunk)
    {
        if ($this->isFinished) {
            return $chunk;
        }

        $this->frame = null;
        return (string) $this->doHandleData($chunk, $this->buffer);
    }

    /**
     * Process raw network data. Data should be used to determine end of this concrete framePicker
     *
     * @param string $chunk Chunk read from socket
     * @param string &$buffer Pointer to internal buffer for collecting data
     *
     * @return string Unprocessed data after the end of frame
     */
    abstract protected function doHandleData($chunk, &$buffer);

    /**
     * Create frame for picked up data
     *
     * @param string $buffer Buffer with collected data
     *
     * @return FrameInterface
     */
    abstract protected function doCreateFrame($buffer);

    /**
     * Sets finished flag
     *
     * @param boolean $isFinished Flag whether framePicker is finished
     *
     * @return void
     */
    protected function setFinished($isFinished)
    {
        $this->isFinished = $isFinished;
    }

    /** {@inheritdoc} */
    public function createFrame()
    {
        if (!$this->frame) {
            $this->frame = $this->doCreateFrame($this->buffer);
        }

        return $this->frame;
    }
}
