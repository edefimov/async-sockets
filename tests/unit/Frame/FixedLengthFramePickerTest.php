<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\FixedLengthFramePicker;

/**
 * Class FixedLengthFramePickerTest
 */
class FixedLengthFramePickerTest extends AbstractFramePickerTest
{
    /** {@inheritdoc} */
    protected function createFramePicker()
    {
        return new FixedLengthFramePicker(5);
    }

    /**
     * testFrameProcessing
     *
     * @param int      $length Length of framePicker Length of framePicker
     * @param string[] $chunks Chunks with data
     * @param string   $expectedFrame Data in frame after processing
     * @param string   $afterFrame Expected data in the end of frame
     * @param bool     $isEof Eof marker
     *
     * @return void
     * @dataProvider frameDataProvider
     */
    public function testFrameProcessing($length, array $chunks, $expectedFrame, $afterFrame, $isEof)
    {
        $picker = new FixedLengthFramePicker($length);

        $unprocessed   = '';
        $remoteAddress = sha1(microtime(true));
        foreach ($chunks as $chunk) {
            $unprocessed = $picker->pickUpData($chunk, $remoteAddress);
        }

        $frame = $picker->createFrame();
        self::assertEquals($expectedFrame, (string) $frame, 'Incorrect frame');
        self::assertEquals($afterFrame, $unprocessed, 'Incorrect data after frame');
        self::assertEquals($isEof, $picker->isEof(), 'Incorrect eof state');
        self::assertEquals($remoteAddress, $frame->getRemoteAddress(), 'Incorrect remote address');
    }

    /**
     * frameDataProvider
     *
     * @param string $targetMethod Target test method
     *
     * @return array
     */
    public function frameDataProvider($targetMethod)
    {
        return $this->dataProviderFromYaml(
            __DIR__,
            __CLASS__,
            __FUNCTION__,
            $targetMethod
        );
    }
}
