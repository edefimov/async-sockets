<?php
/**
 * async-sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\RawFramePicker;

/**
 * Class RawFramePickerTest
 */
class RawFramePickerTest extends AbstractFramePickerTest
{
    /** {@inheritdoc} */
    protected function createFramePicker()
    {
        return new RawFramePicker();
    }

    /**
     * testReadSingleFrame
     *
     * @return void
     */
    public function testReadSingleFrame()
    {
        $data   = md5(microtime(true));
        $picker = $this->createFramePicker();
        $result = $picker->pickUpData($data);

        self::assertEmpty($result, 'Raw frame picker must always collect data on first call');
        self::assertEquals($data, (string) $picker->createFrame(), 'Incorrect frame data');
        self::assertTrue($picker->isEof(), 'EOF flag is not set');
    }

    /**
     * testCantReadSecondTime
     *
     * @return void
     */
    public function testCantReadSecondTime()
    {
        $data   = md5(microtime(true));
        $picker = $this->createFramePicker();
        $picker->pickUpData($data);

        $secondData = sha1(microtime(true));
        $result     = $picker->pickUpData($secondData);
        self::assertEquals($secondData, $result, 'Raw frame picker must ignore data on second call');
        self::assertEquals($data, (string) $picker->createFrame(), 'Incorrect frame data');
    }
}
