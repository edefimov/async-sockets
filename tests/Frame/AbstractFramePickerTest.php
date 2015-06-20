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
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class AbstractFramePickerTest
 */
abstract class AbstractFramePickerTest extends AbstractTestCase
{
    /**
     * Create framePicker for test
     *
     * @return FramePickerInterface
     */
    abstract protected function createFramePicker();

    /**
     * testInitialState
     *
     * @return FramePickerInterface
     */
    public function testInitialState()
    {
        $frame = $this->createFramePicker();

        self::assertFalse($frame->isEof(), 'Frame must not be finished');

        return $frame;
    }
}
