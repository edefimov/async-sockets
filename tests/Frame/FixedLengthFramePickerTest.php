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
    protected function createFrame()
    {
        return new FixedLengthFramePicker(5);
    }

    /** {@inheritdoc} */
    protected function ensureStartOfFrameIsFound(FramePickerInterface $frame)
    {
        $frame->findStartOfFrame('data', 4, '');
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

        self::assertEquals($length, $frame->getLength(), 'Incorrect framePicker length');
        self::assertFalse($frame->isEof(), 'Incorrect eof state');
        self::assertEquals(0, $frame->findStartOfFrame('', 0, ''), 'Incorrect start of framePicker');
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
        $frame = new FixedLengthFramePicker($length);
        $data  = str_repeat('x', $length);
        self::assertEquals(0, $frame->findStartOfFrame($data, $length, ''), 'Incorrect start of framePicker');

        $processed = $frame->handleData($data, $length, '');
        self::assertEquals($length, $processed);
        self::assertTrue($frame->isEof(), 'Incorrect eof state');
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
        $frame = new FixedLengthFramePicker($length);
        $chunk = str_repeat('y', $length);
        $data  = str_repeat('x', $length) ;
        self::assertEquals(0, $frame->findStartOfFrame($chunk, $length, $data), 'Incorrect start of framePicker');
        $processed = $frame->handleData($chunk, $length, $data);

        self::assertEquals($length, $processed);
        self::assertTrue($frame->isEof(), 'Incorrect eof state');
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
        $frame = new FixedLengthFramePicker($length);
        $chunk = str_repeat('y', $length);

        $data = '';
        for ($i = 0; $i < 5; $i++) {
            self::assertEquals(0, $frame->findStartOfFrame($chunk, $length, $data), 'Incorrect start of framePicker');
            $processed = $frame->handleData($data, $length, $data);
            $data     .= $chunk;
            self::assertEquals($i === 0 ? $length : 0, $processed, 'Processed more than framePicker size');
        }

        self::assertTrue($frame->isEof(), 'Incorrect eof state');
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
