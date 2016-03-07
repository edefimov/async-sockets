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

use AsyncSockets\Frame\FixedLengthFramePicker;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class HttpChunkFramePicker
 */
class HttpChunkFramePicker implements FramePickerInterface
{
    /**
     * Content picker
     *
     * @var FramePickerInterface
     */
    private $picker;

    /** {@inheritDoc} */
    public function isEof()
    {
        return $this->picker ? $this->picker->isEof() : false;
    }

    /** {@inheritDoc} */
    public function pickUpData($chunk)
    {
        if (!$this->picker) {
            $length = $this->resolveChunkLength($chunk);
            if ($length === null) {
                return $chunk;
            }

            $this->picker = new FixedLengthFramePicker($length);
        }

        $result = $this->picker->pickUpData($chunk);
        if ($result && isset($result[1]) && $result[0] === "\r" && $result[1] === "\n") {
            $result = substr($result, 2);
        }

        return $result;
    }

    /** {@inheritDoc} */
    public function createFrame()
    {
        return $this->picker ? $this->picker->createFrame() : new Frame('');
    }

    /**
     * Resolve chunk length and adjusts chunk string to skip length header
     *
     * @param string $chunk Original chunk
     *
     * @return int|null
     */
    private function resolveChunkLength(&$chunk)
    {
        $data = explode("\r\n", $chunk, 2);
        if (count($data) < 2) {
            return null;
        }

        $result = hexdec($data[0]);
        $chunk  = substr($chunk, strlen($data[0]) + 2);

        return $result;
    }
}
