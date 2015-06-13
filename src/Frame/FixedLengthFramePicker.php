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
 * Class FixedLengthFramePicker
 */
class FixedLengthFramePicker extends AbstractFramePicker
{
    /**
     * Desired length of framePicker
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
     * FixedLengthFramePicker constructor.
     *
     * @param int $length Length of data for this framePicker
     */
    public function __construct($length)
    {
        parent::__construct();
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
    protected function doHandleData($chunk, $lenChunk, $data)
    {
        $result = min(
            [ $this->length - $this->processedLength, $lenChunk ]
        );

        $this->processedLength += $result;
        if ($this->processedLength === $this->length) {
            $this->setFinished(true);
        }

        return $result;
    }

    /** {@inheritdoc} */
    protected function doFindStartOfFrame($chunk, $lenChunk, $data)
    {
        return 0;
    }
}
