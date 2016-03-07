<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\Frame;

use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class HttpChunkTransferEncodingPicker
 */
class HttpChunkTransferEncodingPicker implements FramePickerInterface
{
    /**
     * List of chunks
     *
     * @var FramePickerInterface[]
     */
    private $chunks = [];

    /**
     * Current chunk index
     *
     * @var int
     */
    private $chunkIndex = -1;

    /** {@inheritDoc} */
    public function isEof()
    {
        return $this->chunks ? (string) $this->chunks[$this->chunkIndex]->createFrame() === '' : false;
    }

    /** {@inheritDoc} */
    public function pickUpData($chunk)
    {
        if (!$this->chunks) {
            $this->chunkIndex = 0;
            $this->chunks[]   = new HttpChunkFramePicker();
        }

        $result = $this->chunks[$this->chunkIndex]->pickUpData($chunk);
        if ($result) {
            $object            = new HttpChunkFramePicker();
            $this->chunkIndex += 1;
            $this->chunks[]    = $object;
            $result            = $object->pickUpData($result);
        }

        return $result;
    }

    /** {@inheritDoc} */
    public function createFrame()
    {
        $buffer = '';
        foreach ($this->chunks as $chunk) {
            $buffer .= (string) $chunk->createFrame();
        }

        return new Frame($buffer);
    }
}
