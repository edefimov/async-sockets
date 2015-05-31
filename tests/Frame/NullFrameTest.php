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

use AsyncSockets\Frame\NullFrame;

/**
 * Class NullFrameTest
 */
class NullFrameTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var NullFrame
     */
    private $frame;

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertTrue($this->frame->isEof(), 'NullFrame::isEof must always return true');
    }

    /**
     * testEveryTimeReturnLength
     *
     * @param int $length Length of frame Length of frame
     *
     * @return void
     * @dataProvider frameSizeDataProvider
     */
    public function testEveryTimeReturnLength($length)
    {
        $data      = str_repeat('x', $length);
        $processed = $this->frame->handleData($data, $length, $data);

        self::assertEquals($length, $processed);
        self::assertTrue($this->frame->isEof(), 'Incorrect eof state');
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
        $this->frame = new NullFrame();
    }
}
