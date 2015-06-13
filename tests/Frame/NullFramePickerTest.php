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

use AsyncSockets\Frame\NullFramePicker;

/**
 * Class NullFramePickerTest
 */
class NullFramePickerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var NullFramePicker
     */
    private $frame;

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertTrue($this->frame->isEof(), 'NullFramePicker::isEof must always return true');
        self::assertEquals(
            0,
            $this->frame->findStartOfFrame('', 0, ''),
            'NullFramePicker::findStartOfFrame must always return 0'
        );
    }

    /**
     * testEveryTimeReturnLength
     *
     * @param int $length Length of framePicker Length of framePicker
     *
     * @return void
     * @dataProvider frameSizeDataProvider
     */
    public function testEveryTimeReturnLength($length)
    {
        $data      = str_repeat('x', $length);
        $processed = $this->frame->handleData($data, $length, '');

        self::assertEquals($length, $processed);
        self::assertTrue($this->frame->isEof(), 'Incorrect eof state');
        self::assertEquals(0, $this->frame->findStartOfFrame($data, $length, ''), 'Incorrect start of framePicker');
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

    protected function setUp()
    {
        parent::setUp();
        $this->frame = new NullFramePicker();
    }
}
