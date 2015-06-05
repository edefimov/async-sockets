<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Assistant;

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Socket\Assistant\FrameProcessor;

/**
 * Class FrameProcessorTest
 */
class FrameProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var FrameProcessor
     */
    private $processor;

    /**
     * Create frame from data, received from dataProvider
     *
     * @param array $data Frame data
     *
     * @return FrameInterface
     */
    private function createFrame(array $data)
    {
        $frame = $this->getMock(
            'AsyncSockets\Frame\FrameInterface',
            ['findStartOfFrame', 'isEof', 'isStarted', 'handleData']
        );

        $retIsStarted        = [ ];
        $retHandleData       = [ ];
        $retFindStartOfFrame = [ ];

        foreach ($data as $item) {
            $retIsStarted[]        = $item[ 0 ];
            $retFindStartOfFrame[] = $item[ 1 ];
            if ($item[1] !== null) {
                $retHandleData[] = $item[2];
            }
        }
        $frame->expects(self::any())
            ->method('isStarted')
            ->will(
                new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($retIsStarted)
            );

        $frame->expects(self::any())
            ->method('findStartOfFrame')
            ->will(
                new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($retFindStartOfFrame)
            );

        $frame->expects(self::any())->method('handleData')
            ->will(
                new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($retHandleData)
            );

        $frame->expects(self::any())->method('isEof')->willReturn(false);

        return $frame;
    }

    /**
     * testDataSearch
     *
     * @param array  $data Data from "socket"
     * @param string $expectedResult Result of reading
     *
     * @return void
     * @dataProvider searchDataProvider
     */
    public function testDataSearch(array $data, $expectedResult)
    {
        $frame  = $this->createFrame($data);
        $result = '';
        foreach ($data as $info) {
            $chunk  = $info[3];
            $result = $this->processor->processReadFrame($frame, $chunk, $result);
        }

        self::assertEquals($expectedResult, $result);
    }

    /**
     * testSequentialFrames
     *
     * @param array $frames Data for frames
     *
     * @return void
     * @dataProvider sequentialFramesDataProvider
     */
    public function testSequentialFrames(array $frames)
    {
        foreach ($frames as $index => $frameInfo) {
            $frameInterface = $this->createFrame($frameInfo[0]);
            $data           = $frameInfo[0];
            $result         = '';
            foreach ($data as $info) {
                $chunk  = $info[3];
                $result = $this->processor->processReadFrame($frameInterface, $chunk, $result);
            }

            self::assertEquals($frameInfo[1], $result, "Unexpected output at frame {$index}");
        }

    }

    /**
     * searchDataProvider
     *
     * @return array
     */
    public function searchDataProvider()
    {
        return [
            [
                [
                    [ false, 0, strlen('first'), 'first' ],
                    [ true, 0, strlen('second'), 'second' ],
                    [ true, 0, strlen('third'), 'third' ],
                ],
                'firstsecondthird'
            ],

            [
                [
                    [ false, null, strlen('first'), 'first' ],
                    [ false, 0, strlen('second'), 'second' ],
                    [ true, 0, 0, 'third' ],
                ],
                'second'
            ],

            [
                [
                    [ false, null, strlen('first'), 'first' ],
                    [ false, -2, strlen('second'), 'second' ],
                    [ true, 0, 2, 'third' ],
                ],
                'stsecondth'
            ],

            [
                [
                    [ false, null, strlen('first'), 'first' ],
                    [ false, 1, 3, 'second' ],
                    [ true, 0, 0, 'third' ],
                ],
                'eco'
            ],

            [
                [
                    [ false, null, strlen('first'), 'first' ],
                    [ false, -4, strlen('second'), 'second' ],
                    [ true, 0, -3, 'third' ],
                ],
                'irstsec'
            ],

            [
                [
                    [ false, null, strlen('first'), 'first' ],
                    [ false, 0, PHP_INT_MAX, 'second' ],
                    [ true, 0, 0, 'third' ],
                ],
                'second'
            ],

            [
                [
                    [ false, null, 0, 'first' ],
                    [ false, null, 0, 'second' ],
                    [ false, null, 0, 'third' ],
                ],
                'firstsecondthird'
            ],
        ];
    }

    /**
     * sequentialFramesDataProvider
     *
     * @return array
     */
    public function sequentialFramesDataProvider()
    {
        return [
            // boundary to boundary test
            [
                [
                    [
                        [
                            [ false, 0, strlen('first'), 'first' ],
                            [ true, 0, strlen('second'), 'second' ],
                            [ true, 0, strlen('third'), 'third' ],
                        ],
                        'firstsecondthird'
                    ],

                    [
                        [
                            [ false, 0, strlen('123'), '123' ],
                            [ true, 0, strlen('456'), '456' ],
                            [ true, 0, strlen('789'), '789' ],
                        ],
                        '123456789'
                    ],
                ]
            ],

            // gap between frames
            [
                [
                    [
                        [
                            [ false, null, strlen('first'), 'first' ],
                            [ false, 0, strlen('second'), 'second' ],
                            [ true, 0, 0, 'third' ],
                        ],
                        'second'
                    ],

                    [
                        [
                            [ false, null, strlen('first'), 'first' ],
                            [ false, -4, strlen('second'), 'second' ],
                            [ true, 0, -3, 'third' ],
                        ],
                        'irstsec'
                    ],
                ]
            ],

            // unhandled data at the end of first frame will be passed to second one
            [
                [
                    [
                        [
                            [ false, null, strlen('first'), 'first' ],
                            [ false, 0, strlen('second'), 'second' ],
                            [ true, 0, 0, 'third' ],
                        ],
                        'second'
                    ],

                    [
                        [
                            [ false, 0, strlen('third') + strlen('first'), 'first' ],
                            [ true, 0, strlen('second'), 'second' ],
                            [ true, 0, 0, 'third' ],
                        ],
                        'thirdfirstsecond'
                    ],

                    [
                        [
                            [ false, strlen('third'), strlen('the last one'), 'the last one' ],
                        ],
                        'the last one'
                    ]
                ]
            ]
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->processor = new FrameProcessor();
    }
}
