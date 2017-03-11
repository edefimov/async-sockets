<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Configuration;

use AsyncSockets\Configuration\StreamContext;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class StreamContextTest
 */
class StreamContextTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testThrowExceptionIfStreamContextInvalid
     *
     * @return void
     * @expectedException \InvalidArgumentException
     */
    public function testThrowExceptionIfStreamContextInvalid()
    {
        new StreamContext(md5(microtime(true)));
    }

    /**
     * testPassStreamContextAsArray
     *
     * @param array|\Traversable $context Arguments for stream context
     * @param array $expected Excpected values in php function call
     *
     * @return void
     * @dataProvider streamContextDataProvider
     */
    public function testPassStreamContextAsArray($context, array $expected)
    {
        $resource = stream_context_create();
        PhpFunctionMocker::getPhpFunctionMocker('stream_context_create')->setCallable(
            function ($options, $params) use ($expected, $resource) {
                self::assertSame($expected['options'], $options, 'Invalid options passed to stream_context_create');
                self::assertSame($expected['params'], $params, 'Invalid params passed to stream_context_create');
                return $resource;
            }
        );

        $object = new StreamContext($context);
        self::assertSame($resource, $object->getResource(), 'Unexpected context resource');
    }

    /**
     * streamContextDataProvider
     *
     * @return array
     */
    public function streamContextDataProvider()
    {
        // passing params, expected params
        return [
            [
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
            ],

            [
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                ],
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                    'params' => [ ]
                ],
            ],

            [
                [
                    'params' => [
                        'value' => 'test'
                    ]
                ],
                [
                    'options' => [ ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
            ],

            [
                [],
                [
                    'options' => [ ],
                    'params' => [ ]
                ],
            ],

            [
                new \ArrayObject(
                    [
                        'params' => [
                            'value' => 'test'
                        ]
                    ]
                ),
                [
                    'options' => [ ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_context_create')->restoreNativeHandler();
    }
}
