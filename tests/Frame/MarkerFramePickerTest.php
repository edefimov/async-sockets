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
     * @return array
     */
    public function stringDataProvider($methodName)
    {
        // start marker, end marker, [ chunks ], expected frame, expected data after frame, is eof
        return [
            ['<', '>', ['<body>'], '<body>', '', true],
            ['<', '>', ['><body>'], '<body>', '', true],
            ['<', '>', ['no marker'], '', '', false],
            [null, 'ker', ['no marker123'], 'no marker', '123', true],
            ['<', '>', ['<body'], '<body', '', false],

            [
                '<body>',
                '</body>',
                ['unknown data <body>clear data</body>mystery data'],
                '<body>clear data</body>',
                'mystery data',
                true
            ],
            [
                '<body>',
                '</body>',
                ['unknown data </body><body>clear data</body>mystery data'],
                '<body>clear data</body>',
                'mystery data',
                true
            ],
            [
                '<body>',
                '</body>',
                ['unknown data --- clear data --- mystery data'],
                '',
                '',
                false
            ],
            [
                null,
                '</body>',
                ['unknown data </body><body>clear data</body>mystery data'],
                'unknown data </body>',
                '<body>clear data</body>mystery data',
                true
            ],
            [
                '<body>',
                '</body>',
                ['unknown data <body>clear data'],
                '<body>clear data',
                '',
                false
            ],

            ['x', 'x', ['xbodyx'], 'xbodyx', '', true],
            ['x', 'x', ['xxbodyx'], 'xx', 'bodyx', true],
            ['x', 'x', ['no marker'], '', '', false],
            ['x', 'x', ['xbody'], 'xbody', '', false],

            [
                '<body>',
                '<body>',
                ['unknown data <body>clear data<body>mystery data'],
                '<body>clear data<body>',
                'mystery data',
                true
            ],
            [
                '<body>',
                '<body>',
                ['unknown data <body><body>clear data</body>mystery data'],
                '<body><body>',
                'clear data</body>mystery data',
                true
            ],
            [
                '<body>',
                '<body>',
                ['unknown data --- clear data --- mystery data'],
                '',
                '',
                false
            ],
            [
                '<body>',
                '<body>',
                ['unknown data <body>clear data'],
                '<body>clear data',
                '',
                false
            ],

            [
                '<',
                '>',
                ['<bo', 'dy>'],
                '<body>',
                '',
                true,
            ],
            [
                '<',
                '>',
                [
                    '><bo', 'dy>'
                ],
                '<body>',
                '',
                true,
            ],
            [
                '<',
                '>',
                [
                    'no marker', 'no marker', 'no marker',
                ],
                '',
                '',
                false,
            ],
            [
                null,
                'ker',
                [
                    'no mar', 'ker',
                ],
                'no marker',
                '',
                true,
            ],

            [
                '<',
                '>',
                [
                    '<body', '-param', ' key="something"',
                ],
                '<body-param key="something"',
                '',
                false,
            ],


            [
                '<body>',
                '</body>',
                [
                    'unknown data <body>', 'clear data</body>mystery data',
                ],
                '<body>clear data</body>',
                'mystery data',
                true
            ],
            [
                '<body>',
                '</body>',
                [
                    'unknown data </body><bo', 'dy>clear data</bo', 'dy>mystery data',
                ],
                '<body>clear data</body>',
                'mystery data',
                true
            ],

            [
                '<body>',
                '</body>',
                [
                    'unknown data -', '-- clear data --- mystery data',
                ],
                '',
                '',
                false
            ],
            [
                null,
                '</body>',
                [
                    'unknown data </body', '><body>clear data</body>mystery data',
                ],
                'unknown data </body>',
                '<body>clear data</body>mystery data',
                true
            ],
            [
                '<body>',
                '</body>',
                [
                    'unknown data <', 'body>clear data',
                ],
                '<body>clear data',
                '',
                false
            ],
        ];
    }
}
