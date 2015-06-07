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
use AsyncSockets\Frame\MarkerFrame;

/**
 * Class MarkerFrameTest
 */
class MarkerFrameTest extends AbstractFrameTest
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
    protected function createFrame()
    {
        $this->startMarker = base64_encode(md5(microtime(true)));
        $this->endMarker   = base64_encode(md5(microtime(true)));
        return new MarkerFrame($this->startMarker, $this->endMarker);
    }

    /** {@inheritdoc} */
    protected function ensureStartOfFrameIsFound(FrameInterface $frame)
    {
        $frame->findStartOfFrame($this->startMarker . 'aaaa', strlen($this->startMarker) + 4, '');
    }

    /** {@inheritdoc} */
    public function testInitialState()
    {
        $frame = parent::testInitialState();

        /** @var MarkerFrame $frame */
        self::assertEquals($this->startMarker, $frame->getStartMarker(), 'Incorrect start marker');
        self::assertEquals($this->endMarker, $frame->getEndMarker(), 'Incorrect end marker');

        return $frame;
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
        self::assertSame($expectedEnd, $actualEnd, 'Incorrect end marker');
        self::assertEquals($isEof, $frame->isEof(), 'Incorrect eof');
    }

    /**
     * testMarkerWillFoundOnBoundary
     *
     * @param string   $start Start marker
     * @param string   $end End marker
     * @param string[] $chunks 0 element is chunk, 1 element is data
     * @param int      $expectedStart Expected result for findStartOfFrame
     * @param int      $expectedEnd Expected result for handleData
     * @param bool     $isEof Expected eof
     *
     * @return void
     * @dataProvider stringBoundaryDataProvider
     */
    public function testMarkerWillFoundOnBoundary($start, $end, array $chunks, $expectedStart, $expectedEnd, $isEof)
    {
        $frame = new MarkerFrame($start, $end);

        $actualStart = $frame->findStartOfFrame($chunks[0][0], strlen($chunks[0][0]), $chunks[0][1]);
        $actualEnd   = null;
        $middleStart = null;
        foreach ($chunks as $pair) {
            $middleStart = $frame->findStartOfFrame($pair[0], strlen($pair[0]), $pair[1]);
            if ($actualStart === null && $middleStart !== null) {
                $actualStart = $middleStart;
                $middleStart = 0;
            }
            $actualEnd   = $frame->handleData($pair[0], strlen($pair[0]), $pair[1]);
        }

        self::assertSame($expectedStart, $actualStart, 'Incorrect start marker');
        self::assertSame($expectedEnd, $actualEnd, 'Incorrect end marker');
        self::assertSame(
            $expectedStart !== null ? 0 : null,
            $middleStart,
            'Incorrect intermediate start marker'
        );
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

    /**
     * stringBoundaryDataProvider
     *
     * @return array
     */
    public function stringBoundaryDataProvider()
    {
        // start marker, end marker, [ [chunk, data] ], expected start, expected handle length, is eof
        return [
            [
                '<',
                '>',
                [
                    ['<bo', ''],
                    ['dy>', '<bo']
                ],
                strpos('<bo', '<'),
                strpos('dy>', '>') + 1,
                true,
            ],
            [
                '<',
                '>',
                [
                    ['><bo', ''],
                    ['dy>', '><bo']
                ],
                strpos('><bo', '<'),
                strpos('dy>', '>', 1) + 1,
                true,
            ],
            [
                '<',
                '>',
                [
                    ['no marker', ''],
                    ['no marker', 'no marker'],
                    ['no marker', 'no markerno marker'],
                ],
                null,
                0,
                false,
            ],
            [
                null,
                'ker',
                [
                    ['no mar', ''],
                    ['ker', 'no mar'],
                ],
                0,
                0 + 3,
                true,
            ],

            [
                '<',
                '>',
                [
                    ['<body', ''],
                    ['-param', '<body'],
                    ['key="something"', '<body-param'],
                ],
                strpos('<body', '<'),
                strlen('key="something"'),
                false,
            ],


            [
                '<body>',
                '</body>',
                [
                    ['unknown data <body>', ''],
                    ['clear data</body>mystery data', 'unknown data <body>'],
                ],
                strpos('unknown data <body>', '<body>'),
                strpos('clear data</body>mystery data', '</body>') + 7,
                true
            ],
            [
                '<body>',
                '</body>',
                [
                    ['unknown data </body><bo', ''],
                    ['dy>clear data</bo', 'unknown data </body><bo'],
                    ['dy>mystery data', 'unknown data </body><body>clear data</bo']
                ],
                -3,
                -4 + 7,
                true
            ],

            [
                '<body>',
                '</body>',
                [
                    ['unknown data -', ''],
                    ['-- clear data --- mystery data', 'unknown data -'],
                ],
                null,
                0,
                false
            ],
            [
                null,
                '</body>',
                [
                    ['unknown data </body', ''],
                    ['><body>clear data</body>mystery data', 'unknown data </body'],
                ],
                0,
                -6 + 7,
                true
            ],
            [
                '<body>',
                '</body>',
                [
                    ['unknown data <', ''],
                    ['body>clear data', 'unknown data <'],
                ],
                -1,
                strlen('body>clear data'),
                false
            ],
        ];
    }
}
