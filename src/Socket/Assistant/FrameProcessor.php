<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Assistant;

use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class FrameProcessor
 */
class FrameProcessor
{
    /**
     * Unhandled portion of data at the end of framePicker
     *
     * @var string
     */
    private $unhandledData = '';

    /**
     * Process data by framePicker
     *
     * @param FramePickerInterface $frame Frame to process data
     * @param string         $chunk Chunk from network
     * @param string         $data Data from previous call to this method
     *
     * @return string Processed chunk
     */
    public function processReadFrame(FramePickerInterface $frame, $chunk, $data)
    {
        $chunk               = $this->unhandledData . $chunk;
        $lenChunk            = strlen($chunk);
        $result              = '';
        $this->unhandledData = '';

        if (!$frame->isStarted()) {
            $startOffset = $frame->findStartOfFrame($chunk, $lenChunk, $data);
            if ($startOffset === null) {
                return $data . $chunk;
            }

            if ($startOffset < 0) {
                $result      = substr($data, $startOffset, -$startOffset);
                $startOffset = 0;
            }
        } else {
            $startOffset = 0;
            $result      = $data;
        }

        $processed = $frame->handleData($chunk, $lenChunk, $data);
        if ($processed < 0) {
            $result = substr($result, 0, $processed);
        } elseif ($processed > $lenChunk) {
            $processed = $lenChunk;
        }

        if (0 <= $processed && $processed <= $lenChunk) {
            $chunkData = substr($chunk, $startOffset, $processed);
            $result    = $startOffset > 0 ? $chunkData : $result . $chunkData;
            $this->unhandledData = $processed < $lenChunk ? substr($chunk, $processed) : '';
        }

        return $result;
    }
}
