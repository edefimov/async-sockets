<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Frame;

/**
 * Class NullFramePicker. Special type of framePicker, indicates that data should be read
 * until all data is not received
 */
final class NullFramePicker implements FramePickerInterface
{
    /**
     * Data buffer
     *
     * @var string
     */
    private $buffer;

    /**
     * Remote socket address
     *
     * @var string
     */
    private $remoteAddress;

    /** {@inheritdoc} */
    public function isEof()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function pickUpData($chunk, $remoteAddress)
    {
        $this->buffer       .= $chunk;
        $this->remoteAddress = $remoteAddress;
        return '';
    }

    /** {@inheritdoc} */
    public function createFrame()
    {
        return new Frame($this->buffer, $this->remoteAddress);
    }
}
