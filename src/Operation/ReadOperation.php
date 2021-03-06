<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Operation;

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\RawFramePicker;

/**
 * Class ReadOperation
 */
class ReadOperation implements OperationInterface
{
    /**
     * Frame picker object
     *
     * @var FramePickerInterface
     */
    private $framePicker;

    /**
     * ReadOperation constructor.
     *
     * @param FramePickerInterface $framePicker Frame picker object
     */
    public function __construct(FramePickerInterface $framePicker = null)
    {
        $this->framePicker = $framePicker ?: new RawFramePicker();
    }


    /** {@inheritdoc} */
    public function getTypes()
    {
        return [self::OPERATION_READ];
    }

    /**
     * Return FramePicker
     *
     * @return FramePickerInterface
     */
    public function getFramePicker()
    {
        return $this->framePicker;
    }
}
