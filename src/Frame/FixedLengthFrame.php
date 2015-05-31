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
 * Class FixedLengthFrame
 */
class FixedLengthFrame implements FrameInterface
{
    /**
     * Desired length of frame
     *
     * @var int
     */
    private $length;

    /**
     * Number of processed bytes
     *
     * @var int
     */
    private $processedLength;

    /**
     * FixedLengthFrame constructor.
     *
     * @param int $length Length of data for this frame
     */
    public function __construct($length)
    {
        $this->length          = (int) $length;
        $this->processedLength = 0;
    }

    /**
     * Return Length
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }


    /** {@inheritdoc} */
    public function isEof()
    {
        return $this->processedLength === $this->length;
    }

    /** {@inheritdoc} */
    public function handleData($chunk, $lenChunk, $data)
    {
        if ($this->isEof()) {
            return 0;
        }
        
        $result = $this->processedLength + $lenChunk > $this->length ?
                    $this->processedLength + $lenChunk - $this->length :
                    $lenChunk;

        $this->processedLength += $result;
        return $result;
    }
}
