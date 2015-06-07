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

use AsyncSockets\Frame\FrameInterface;

/**
 * Class AbstractFrameTest
 */
abstract class AbstractFrameTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create frame for test
     *
     * @return FrameInterface
     */
    abstract protected function createFrame();

    /**
     * ensureStartOfFrameIsFound
     *
     * @param FrameInterface $frame Test object
     *
     * @return void
     */
    abstract protected function ensureStartOfFrameIsFound(FrameInterface $frame);

    /**
     * testInitialState
     *
     * @return FrameInterface
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
     * @param FrameInterface $frame Test object
     *
     * @return void
     * @depends testInitialState
     */
    public function testHandleDataIfNotStarted(FrameInterface $frame)
    {
        self::assertSame(0, $frame->handleData('test', 4, ''), 'Not started frame must return 0');
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
