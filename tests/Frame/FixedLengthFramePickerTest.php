<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\FixedLengthFramePicker;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class FixedLengthFramePickerTest
 */
class FixedLengthFramePickerTest extends AbstractFramePickerTest
{
    /** {@inheritdoc} */
    protected function createFramePicker()
    {
        return new FixedLengthFramePicker(5);
    }

    /**
     * testGetters
     *
     * @param int $length Length of framePicker
     *
     * @return void
     * @dataProvider frameSizeDataProvider
     */
    public function testGetters($length)
    {
        $frame  = new FixedLengthFramePicker($length);

        self::assertFalse($frame->isEof(), 'Incorrect eof state');
    }

    /**
     * testProcessingByFullLength
     *
     * @param int $length Length of framePicker Length of framePicker
     *
     * @return void
     * @depends testGetters
     * @dataProvider frameSizeDataProvider
     */
    public function testProcessingByFullLength($length)
    {
        $picker      = new FixedLengthFramePicker($length);
        $data        = str_repeat('x', $length);
        $unprocessed = $picker->pickUpData($data);
        self::assertEquals($length, strlen((string) $picker->createFrame()));
        self::assertEmpty($unprocessed);
        self::assertTrue($picker->isEof(), 'Incorrect eof state');
    }

    /**
     * testProcessMoreThanSize
     *
     * @param int $length Length of framePicker Length of framePicker
     *
     * @return void
     * @depends testGetters
     * @dataProvider frameSizeDataProvider
     */
    public function testProcessMoreThanSize($length)
    {
        $picker      = new FixedLengthFramePicker($length);
        $chunk       = str_repeat('y', $length);
        $afterFrame  = str_repeat('x', $length);
        $unprocessed = $picker->pickUpData($chunk . $afterFrame);

        self::assertEquals($length, strlen((string) $picker->createFrame()), 'Frame length is wrong');
        self::assertEquals($chunk, (string) $picker->createFrame(), 'Incorrect data inside frame');
        self::assertEquals($afterFrame, $unprocessed, 'Incorrect data at the end of frame');
        self::assertTrue($picker->isEof(), 'Incorrect eof state');
    }

    /**
     * testOverfill
     *
     * @param int $length Length of framePicker Length of framePicker
     *
     * @return void
     * @depends testGetters
     * @dataProvider frameSizeDataProvider
     */
    public function testOverfill($length)
    {
        $picker = new FixedLengthFramePicker($length);
        $chunk = str_repeat('y', $length);

        for ($i = 0; $i < 5; $i++) {
            $unprocessed = $picker->pickUpData($chunk);
            self::assertEquals($i === 0 ? '' : $chunk, $unprocessed, 'Processed more than framePicker size');
        }

        self::assertTrue($picker->isEof(), 'Incorrect eof state');
    }

    /**
     * frameSizeDataProvider
     *
     * @return array
     */
    public function frameSizeDataProvider()
    {
        $result = [];
        for ($i = 0; $i < 5; $i++) {
            $result[] = [mt_rand(1024, 4096)];
        }
        return $result;
    }
}
