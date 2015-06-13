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

/**
 * Class AbstractFramePickerTest
 */
abstract class AbstractFramePickerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create framePicker for test
     *
     * @return FramePickerInterface
     */
    abstract protected function createFrame();

    /**
     * ensureStartOfFrameIsFound
     *
     * @param FramePickerInterface $frame Test object
     *
     * @return void
     */
    abstract protected function ensureStartOfFrameIsFound(FramePickerInterface $frame);

    /**
     * testInitialState
     *
     * @return FramePickerInterface
     */
    public function testInitialState()
    {
        $frame = $this->createFrame();

        self::assertFalse($frame->isStarted(), 'Frame must not be started');
        self::assertFalse($frame->isEof(), 'Frame must not be finished');

        return $frame;
    }

    /**
     * testHandleDataIfNotStarted
     *
     * @param FramePickerInterface $frame Test object
     *
     * @return void
     * @depends testInitialState
     */
    public function testHandleDataIfNotStarted(FramePickerInterface $frame)
    {
        self::assertSame(0, $frame->handleData('test', 4, ''), 'Not started framePicker must return 0');
    }

    /**
     * testStartedCorrectly
     *
     * @return void
     * @depends testInitialState
     */
    public function testStartedCorrectly()
    {
        $frame = $this->createFrame();
        $this->ensureStartOfFrameIsFound($frame);
        self::assertTrue($frame->isStarted(), 'Frame must be started');
        self::assertFalse($frame->isEof(), 'Frame must be started');
    }
}
