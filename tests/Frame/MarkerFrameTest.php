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

use AsyncSockets\Frame\MarkerFrame;

/**
 * Class MarkerFrameTest
 */
class MarkerFrameTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $start = base64_encode(md5(microtime(true)));
        $end   = base64_encode(md5(microtime(true)));
        $frame = new MarkerFrame($start, $end);

        self::assertEquals($start, $frame->getStartMarker(), 'Incorrect start marker');
        self::assertEquals($end, $frame->getEndMarker(), 'Incorrect end marker');
        self::assertFalse($frame->isEof(), 'Incorrect end of frame');
    }

    /**
     * testSearchMarkerInString
     *
     * @param string $start Start marker
     * @param string $end End marker
     * @param string $chunk Test data
     * @param int    $expectedStart Expected result for findStartOfFrame
     * @param int    $expectedEnd Expected result for handleData
     * @param bool   $isEof Expected eof
     *
     * @return void
     * @dataProvider stringDataProvider
     */
    public function testSearchMarkerInString($start, $end, $chunk, $expectedStart, $expectedEnd, $isEof)
    {
        $frame = new MarkerFrame($start, $end);

        $actualStart = $frame->findStartOfFrame($chunk, strlen($chunk), '');
        $actualEnd   = $frame->handleData($chunk, strlen($chunk), '');

        self::assertSame($expectedStart, $actualStart, 'Incorrect start marker');
        self::assertSame($expectedEnd, $actualEnd, 'Incorrect start marker');
        self::assertEquals($isEof, $frame->isEof(), 'Incorrect eof');
    }

    /**
     * stringDataProvider
     *
     * @return array
     */
    public function stringDataProvider()
    {
        // start marker, end marker, chunk, expected start, expected handle length, is eof
        return [
            ['<', '>', '<body>', strpos('<body>', '<'), strpos('<body>', '>')+1, true],
            ['<', '>', '><body>', strpos('><body>', '<'), strpos('><body>', '>', 1)+1, true],
            ['<', '>', 'no marker', null, 0, false],
            [null, 'ker', 'no marker', 0, strpos('no marker', 'ker')+3, true],
            ['<', '>', '<body', strpos('<body', '<'), strlen('<body'), false],

            [
                '<body>',
                '</body>',
                'unknown data <body>clear data</body>mystery data',
                strpos('unknown data <body>clear data</body>mystery data', '<body>'),
                strpos('unknown data <body>clear data</body>mystery data', '</body>') + 7,
                true
            ],
            [
                '<body>',
                '</body>',
                'unknown data </body><body>clear data</body>mystery data',
                strpos('unknown data </body><body>clear data</body>mystery data', '<body>'),
                strpos('unknown data </body><body>clear data</body>mystery data', '</body>', 26) + 7,
                true
            ],
            [
                '<body>',
                '</body>',
                'unknown data --- clear data --- mystery data',
                null,
                0,
                false
            ],
            [
                null,
                '</body>',
                'unknown data </body><body>clear data</body>mystery data',
                0,
                strpos('unknown data </body><body>clear data</body>mystery data', '</body>') + 7,
                true
            ],
            [
                '<body>',
                '</body>',
                'unknown data <body>clear data',
                strpos('unknown data <body>clear data', '<body>'),
                strlen('unknown data <body>clear data'),
                false
            ],

            ['x', 'x', 'xbodyx', strpos('xbodyx', 'x'), strpos('xbodyx', 'x', 1)+1, true],
            ['x', 'x', 'xxbodyx', strpos('xxbodyx', 'x'), strpos('xxbodyx', 'x', 1)+1, true],
            ['x', 'x', 'no marker', null, 0, false],
            ['x', 'x', 'xbody', strpos('xbody', 'x'), strlen('xbody'), false],

            [
                '<body>',
                '<body>',
                'unknown data <body>clear data<body>mystery data',
                strpos('unknown data <body>clear data<body>mystery data', '<body>'),
                strpos('unknown data <body>clear data<body>mystery data', '<body>', 19) + 6,
                true
            ],
            [
                '<body>',
                '<body>',
                'unknown data <body><body>clear data</body>mystery data',
                strpos('unknown data <body><body>clear data</body>mystery data', '<body>'),
                strpos('unknown data <body><body>clear data</body>mystery data', '<body>', 19) + 6,
                true
            ],
            [
                '<body>',
                '<body>',
                'unknown data --- clear data --- mystery data',
                null,
                0,
                false
            ],
            [
                '<body>',
                '<body>',
                'unknown data <body>clear data',
                strpos('unknown data <body>clear data', '<body>'),
                strlen('unknown data <body>clear data'),
                false
            ],
        ];
    }
}
