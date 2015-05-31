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

use AsyncSockets\Frame\FixedLengthFrame;

/**
 * Class FixedLengthFrameTest
 */
class FixedLengthFrameTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testGetters
     *
     * @param int $length Length of frame
     *
     * @return void
     * @dataProvider frameSizeDataProvider
     */
    public function testGetters($length)
    {
        $frame  = new FixedLengthFrame($length);

        self::assertEquals($length, $frame->getLength(), 'Incorrect frame length');
        self::assertFalse($frame->isEof(), 'Incorrect eof state');
        self::assertEquals(0, $frame->findStartOfFrame('', 0, ''), 'Incorrect start of frame');
    }

    /**
     * testProcessingByFullLength
     *
     * @param int $length Length of frame Length of frame
     *
     * @return void
     * @depends testGetters
     * @dataProvider frameSizeDataProvider
     */
    public function testProcessingByFullLength($length)
    {
        $frame = new FixedLengthFrame($length);
        $data  = str_repeat('x', $length);
        self::assertEquals(0, $frame->findStartOfFrame($data, $length, ''), 'Incorrect start of frame');

        $processed = $frame->handleData($data, $length, '');
        self::assertEquals($length, $processed);
        self::assertTrue($frame->isEof(), 'Incorrect eof state');
    }

    /**
     * testProcessMoreThanSize
     *
     * @param int $length Length of frame Length of frame
     *
     * @return void
     * @depends testGetters
     * @dataProvider frameSizeDataProvider
     */
    public function testProcessMoreThanSize($length)
    {
        $frame = new FixedLengthFrame($length);
        $chunk = str_repeat('y', $length);
        $data  = str_repeat('x', $length) ;
        self::assertEquals(0, $frame->findStartOfFrame($chunk, $length, $data), 'Incorrect start of frame');
        $processed = $frame->handleData($chunk, $length, $data);

        self::assertEquals($length, $processed);
        self::assertTrue($frame->isEof(), 'Incorrect eof state');
    }

    /**
     * testOverfill
     *
     * @param int $length Length of frame Length of frame
     *
     * @return void
     * @depends testGetters
     * @dataProvider frameSizeDataProvider
     */
    public function testOverfill($length)
    {
        $frame = new FixedLengthFrame($length);
        $chunk = str_repeat('y', $length);

        $data = '';
        for ($i = 0; $i < 5; $i++) {
            self::assertEquals(0, $frame->findStartOfFrame($chunk, $length, $data), 'Incorrect start of frame');
            $processed = $frame->handleData($data, $length, $data);
            $data     .= $chunk;
            self::assertEquals($i === 0 ? $length : 0, $processed, 'Processed more than frame size');
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
