<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\Socket\ClientSocket;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class ClientSocketTest
 */
class ClientSocketTest extends AbstractSocketTest
{
    /**
     * testExceptionWillBeThrowsOnCreateFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionCode 500
     * @expectedExceptionMessage Tested socket creation
     */
    public function testExceptionWillBeThrowsOnCreateFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client');
        $mocker->setCallable(function ($remoteSocket, &$errno, &$errstr) {
            self::assertEquals('php://temp', $remoteSocket, 'Incorrect address passed to stream_socket_client');
            $errno  = 500;
            $errstr = 'Tested socket creation';
            return false;
        });

        $this->socket->open('php://temp');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client');
        $mocker->setCallable(function () {
            return fopen('php://temp', 'rw');
        });
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->restoreNativeHandler();
    }

    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        return new ClientSocket();
    }

    /**
     * testReadingPartialContent
     *
     * @return void
     */
    public function testReadingPartialContent()
    {
        $testString = "HTTP 200 OK\nServer: test-reader\n\n";
        $counter    = 0;

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('fread');
        $mocker->setCallable(function () use ($testString, &$counter) {
            return $counter < strlen($testString) ? $testString[$counter++] : '';
        });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () use ($testString, &$counter) {
                return $counter < strlen($testString) ? $testString[$counter] : '';
            }
        );

        $this->socket->open('it has no meaning here');
        $retString = $this->socket->read()->data();
        self::assertEquals($testString, $retString, 'Unexpected result was read');
    }

    /**
     * testChunkReading
     *
     * @return void
     */
    public function testChunkReading()
    {
        $data      = 'I will pass this test';
        $splitData = str_split($data, 1);
        $freadMock = $this->getMock('Countable', ['count']);
        $freadMock->expects(self::any())
            ->method('count')
            ->will(new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($splitData));
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(function () {
            return 1;
        });
        PhpFunctionMocker::getPhpFunctionMocker('fread')->setCallable(function () use ($freadMock) {
            /** @var \Countable $freadMock */
            return $freadMock->count();
        });

        $streamSocketRecvFromData = [];
        foreach ($splitData as $letter) {
            $streamSocketRecvFromData[] = $letter;
            $streamSocketRecvFromData[] = false;
            if (mt_rand(1, 10) % 2) {
                $streamSocketRecvFromData[] = false;
            }
        }
        $streamSocketRecvFromData[] = '';
        $socketReadMock = $this->getMock('Countable', ['count']);
        $socketReadMock->expects(self::any())
            ->method('count')
            ->will(new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($streamSocketRecvFromData));
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () use ($socketReadMock) {
                /** @var \Countable $socketReadMock */
                return $socketReadMock->count();
            }
        );

        $responseText = '';
        $this->socket->open('it has no meaning here');
        do {
            $response      = $this->socket->read(null);
            $responseText .= (string) $response;
        } while ($response instanceof PartialFrame);

        self::assertEquals($data, $responseText, 'Received data is incorrect');
    }

    /** {@inheritdoc} */
    public function ioDataProvider()
    {
        $falseFunction = function () {
            return false;
        };

        return array_merge(
            parent::ioDataProvider(),
            [
                // testExceptionWillBeThrownOnReadError
                [
                    [
                        ['open', ['no matter']],
                        ['read', []],
                    ],
                    [
                        'stream_select' => $falseFunction,
                    ],
                    'Failed to read data.'
                ],

                // testActualReadingFail
                [
                    [
                        ['open', ['no matter']],
                        ['read', []],
                    ],
                    [
                        'stream_select' => function () {
                            return 1;
                        },
                        'fread' => $falseFunction,
                        'stream_socket_recvfrom' => function () {
                            return 'x';
                        }
                    ],
                    'Failed to read data.'
                ],

                // testLossConnectionOnReading
                [
                    [
                        ['open', ['no matter']],
                        ['read', []],
                    ],
                    [
                        'stream_select' => function () {
                            return 1;
                        },
                        'fread' => function () use ($falseFunction) {
                            PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
                                $falseFunction
                            );
                            return '';
                        },
                        'stream_socket_recvfrom' => function () {
                            return 'x';
                        }
                    ],
                    'Remote connection has been lost.'
                ],

                // testConnectionRefusedOnRead
                [
                    [
                        ['open', ['no matter']],
                        ['read', []],
                    ],
                    [
                        'stream_socket_get_name' => $falseFunction
                    ],
                    'Connection refused.'
                ],

                // testConnectionRefusedOnWrite
                [
                    [
                        ['open', ['no matter']],
                        ['write', ['some data to write']],
                    ],
                    [
                        'stream_socket_get_name' => $falseFunction
                    ],
                    'Connection refused.'
                ],

                // testLossConnectionOnWriting
                [
                    [
                        ['open', ['no matter']],
                        ['write', ['some data to write']],
                    ],
                    [
                        'stream_select' => function () {
                            return 1;
                        },
                        'fwrite' => function () use ($falseFunction) {
                            PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
                                $falseFunction
                            );
                            return 0;
                        },
                        'stream_socket_sendto' => function ($handle, $data) {
                            return strlen($data);
                        }
                    ],
                    'Remote connection has been lost.'
                ],
            ]
        );
    }
}
