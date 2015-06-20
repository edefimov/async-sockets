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

use AsyncSockets\Frame\MarkerFramePicker;

/**
 * Class MarkerFramePickerTest
 */
class MarkerFramePickerTest extends AbstractFramePickerTest
{
    /**
     * Start marker
     *
     * @var string
     */
    private $startMarker;

    /**
     * End marker
     *
     * @var string
     */
    private $endMarker;

    /** {@inheritdoc} */
    protected function createFramePicker()
    {
        $this->startMarker = base64_encode(md5(microtime(true)));
        $this->endMarker   = base64_encode(md5(microtime(true)));
        return new MarkerFramePicker($this->startMarker, $this->endMarker);
    }

    /**
     * testSearchMarkerInString
     *
     * @param string   $start Start marker
     * @param string   $end End marker
     * @param string[] $chunks List of chunks
     * @param string   $expectedFrame Expected data inside frame
     * @param string   $afterFrame Expected data after frame
     * @param bool     $isEof Expected eof
     *
     * @return void
     * @dataProvider stringDataProvider
     */
    public function testSearchMarkerInString($start, $end, array $chunks, $expectedFrame, $afterFrame, $isEof)
    {
        $picker = new MarkerFramePicker($start, $end);

        $actualEnd = '';
        foreach ($chunks as $chunk) {
            $actualEnd = $picker->pickUpData($chunk);
        }

        $frame = $picker->createFrame();
        self::assertEquals($expectedFrame, $frame, 'Incorrect frame');
        self::assertEquals($afterFrame, $actualEnd, 'Incorrect data after frame');
        self::assertEquals($isEof, $picker->isEof(), 'Incorrect eof');
    }

    /**
     * stringDataProvider
     *
     * @param string $methodName Target test method
     *
     * @return array
     */
    public function stringDataProvider($methodName)
    {
        return $this->dataProviderFromYaml(
            __DIR__,
            __FUNCTION__,
            $methodName
        );
    }
}
