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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;

/**
 * Class NullFramePickerTest
 */
class NullFramePickerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create framePicker for test
     *
     * @return FramePickerInterface
     */
    protected function createFramePicker()
    {
        return new NullFramePicker();
    }

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertTrue($this->createFramePicker()->isEof(), 'NullFramePicker::isEof must always return true');
    }

    /**
     * testPickUpEverything
     *
     * @param int $length Length of framePicker Length of framePicker
     *
     * @return void
     * @dataProvider frameSizeDataProvider
     */
    public function testPickUpEverything($length)
    {
        $picker    = $this->createFramePicker();
        $data      = str_repeat('x', $length);
        $processed = $picker->pickUpData($data, $data);

        $frame = $picker->createFrame();
        self::assertEquals($length, strlen((string) $frame));
        self::assertEquals($data, (string) $frame, 'Incorrect frame data');
        self::assertEmpty($processed, 'Null frame must not leave any data after frame');
        self::assertTrue($picker->isEof(), 'Incorrect eof state');
        self::assertSame($data, $frame->getRemoteAddress(), 'Incorrect remote address');
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
