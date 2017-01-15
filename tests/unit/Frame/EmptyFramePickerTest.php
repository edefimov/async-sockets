<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\EmptyFramePicker;

/**
 * Class EmptyFramePickerTest
 */
class EmptyFramePickerTest extends AbstractFramePickerTest
{
    /** {@inheritdoc} */
    protected function createFramePicker()
    {
        return new EmptyFramePicker();
    }

    /**
     * testPickingData
     *
     * @return void
     */
    public function testPickingData()
    {
        $picker = $this->createFramePicker();
        $data   = sha1(microtime());

        $actual = $picker->pickUpData($data, $data);
        self::assertSame($data, $actual, 'Incorrect processed data returned');
        self::assertTrue($picker->isEof(), 'End of frame must be set');

        $frame = $picker->createFrame();
        self::assertInstanceOf('AsyncSockets\Frame\EmptyFrame', $frame, 'Incorrect frame type');
        self::assertSame($data, $frame->getRemoteAddress(), 'Incorrect remote address');
    }
}
