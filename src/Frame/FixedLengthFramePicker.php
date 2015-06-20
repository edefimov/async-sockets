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
     * FixedLengthFramePicker constructor.
     *
     * @param int $length Length of data for this framePicker
     */
    public function __construct($length)
    {
        parent::__construct();
        $this->length = (int) $length;
    }

    /** {@inheritdoc} */
    protected function doHandleData($chunk, &$buffer)
    {
        $chunkLength   = strlen($chunk);
        $dataLength    = min($this->length - strlen($buffer), $chunkLength);
        $buffer       .= substr($chunk, 0, $dataLength);
        $isEndReached  = strlen($buffer) === $this->length;

        if ($isEndReached) {
            $this->setFinished(true);
            return substr($chunk, $dataLength);
        }

        return '';
    }

    /** {@inheritdoc} */
    protected function doCreateFrame($buffer)
    {
        return new Frame($buffer);
    }
}
