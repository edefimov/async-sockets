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

use AsyncSockets\Socket\ChunkSocketResponse;
use AsyncSockets\Socket\SocketInterface;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class AbstractSocketTest
 *
 * @SuppressWarnings("unused")
 * @SuppressWarnings("TooManyMethods")
 */
class AbstractSocketTest extends \PHPUnit_Framework_TestCase
{
    /**
     * SocketInterface
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * testExceptionWillBeThrowsOnSetBlockingFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionWillBeThrowsOnSetBlockingFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking');
        $this->socket->open('php://temp');

        $mocker->setCallable(function ($resource, $isBlocking) {
            self::assertEquals(false, $isBlocking, 'Unexpected value passed');
            return false;
        });

        $this->socket->setBlocking(false);
    }

    /**
     * testBlockingModeWillChange
     *
     * @return void
     */
    public function testBlockingModeWillChange()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->setCallable(
            function ($resource) {
                return \stream_get_meta_data($resource) + [ 'blocked' => true ];
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking')->setCallable(
            function ($resource, $isBlocking) {
                self::assertEquals(false, $isBlocking, 'Unexpected value passed');
                return \stream_set_blocking($resource, $isBlocking);
            }
        );

        $this->socket->setBlocking(false);
        $this->socket->open('php://temp');
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
        $retString = $this->socket->read()->getData();
        self::assertEquals($testString, $retString, 'Unexpected result was read');
    }

    /**
     * testWritePartialContent
     *
     * @return void
     */
    public function testWritePartialContent()
    {
        $testString = "GET / HTTP/1.1\nHost: github.com\n\n";
        $counter    = 0;
        $retString  = '';

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('fwrite');
        $mocker->setCallable(function ($handle, $data) use ($testString, &$counter, &$retString) {
            if ($data && $counter < strlen($testString)) {
                ++$counter;
                $retString .= $data[0];
                return 1;
            }

            return 0;
        });

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto');
        $mocker->setCallable(function ($handle, $data) {
            return strlen($data);
        });

        $this->socket->open('it has no meaning here');
        $this->socket->write($testString);
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

        $response = null;
        $this->socket->open('it has no meaning here');
        do {
            $response = $this->socket->read(null, $response);
        } while ($response instanceof ChunkSocketResponse);

        self::assertInstanceOf('AsyncSockets\Socket\SocketResponse', $response);
        self::assertNotInstanceOf(
            'AsyncSockets\Socket\ChunkSocketResponse',
            $response,
            'Final response must not be chunk'
        );

        self::assertEquals($data, (string) $response, 'Received data is incorrect');
    }

    /**
     * testNothingHappenIfNotSelected
     *
     * @return void
     */
    public function testNothingHappenIfNotSelected()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(function () {
            return 0;
        });

        $this->socket->open('it has no meaning here');
        self::assertInstanceOf('AsyncSockets\Socket\SocketResponse', $this->socket->read(), 'Strange response');
    }

    /**
     * testIoFailures
     *
     * @param array      $methodCalls Method calls on socket: [methodName, arguments]
     * @param callable[] $mocks PHP functions to mock
     * @param string     $exceptionMessage Exception message to test
     *
     * @return void
     * @dataProvider ioDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testIoFailures(array $methodCalls, array $mocks, $exceptionMessage = '')
    {
        $this->setExpectedException('AsyncSockets\Exception\NetworkSocketException', $exceptionMessage);
        foreach ($mocks as $name => $callable) {
            PhpFunctionMocker::getPhpFunctionMocker($name)->setCallable($callable);
        }

        foreach ($methodCalls as $methodCall) {
            call_user_func_array([$this->socket, $methodCall[0]], $methodCall[1]);
        }
    }

    /**
     * testCloseWillBeInvokedOnDestruct
     *
     * @return void
     */
    public function testCloseWillBeInvokedOnDestruct()
    {
        if (!method_exists($this->socket, '__destruct')) {
            self::fail(
                'You must implement __destruct in SocketInterface implementation and call \'close\' method inside'
            );
        }

        $class  = get_class($this->socket);
        $object = $this->getMockBuilder($class)
            ->setMethods(['close'])
            ->getMock();
        $object->expects(self::once())
            ->method('close')
            ->with();

        /** @var SocketInterface $object */
        $object->__destruct();
    }

    /**
     * ioDataProvider
     *
     * @return array
     */
    public function ioDataProvider()
    {
        // data for testing read/write. Format:
        // method, arguments, mock functions
        return [
            // testReadFailureIfNoResource
            [
                [
                    ['read', []],
                ],
                [],
                'Can not start io operation on uninitialized socket.'
            ],

            // testConnectionRefusedOnRead
            [
                [
                    ['open', ['no matter']],
                    ['read', []],
                ],
                [
                    'stream_socket_get_name' => function () {
                        return false;
                    }
                ],
                'Connection refused.'
            ],

            // testExceptionWillBeThrownOnReadError
            [
                [
                    ['open', ['no matter']],
                    ['read', []],
                ],
                [
                    'stream_select' => function () {
                        return false;
                    },
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
                    'fread' => function () {
                        return false;
                    },
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
                    'fread' => function () {
                        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
                            function () {
                                return false;
                            }
                        );
                        return 0;
                    },
                    'stream_socket_recvfrom' => function () {
                        return 'x';
                    }
                ],
                'Remote connection has been lost.'
            ],

            // testWriteFailureIfNoResource
            [
                [
                    ['write', ['no matter']],
                ],
                [

                ],
                'Can not start io operation on uninitialized socket.'
            ],

            // testConnectionRefusedOnWrite
            [
                [
                    ['open', ['no matter']],
                    ['write', ['some data to write']],
                ],
                [
                    'stream_socket_get_name' => function () {
                        return false;
                    }
                ],
                'Connection refused.'
            ],

            // testExceptionWillBeThrownOnWriteError
            [
                [
                    ['open', ['no matter']],
                    ['write', ['some data to write']],
                ],
                [
                    'stream_select' => function () {
                        return false;
                    },
                ],
                'Failed to send data.'
            ],

            // testWriteSocketSendToFail
            [
                [
                    ['open', ['no matter']],
                    ['write', ['some data to write']],
                ],
                [
                    'stream_select' => function () {
                        return 1;
                    },
                    'stream_socket_sendto' => function () {
                        return -1;
                    }
                ],
                'Failed to send data.'
            ],

            // testActualWritingFail
            [
                [
                    ['open', ['no matter']],
                    ['write', ['some data to write']],
                ],
                [
                    'stream_select' => function () {
                        return 1;
                    },
                    'fwrite' => function () {
                        return false;
                    },
                    'stream_socket_sendto' => function ($handle, $data) {
                        return strlen($data);
                    }
                ],
                'Failed to send data.'
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
                    'fwrite' => function () {
                        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
                            function () {
                                return false;
                            }
                        );
                        return 0;
                    },
                    'stream_socket_sendto' => function ($handle, $data) {
                        return strlen($data);
                    }
                ],
                'Remote connection has been lost.'
            ],

            // testWriteFailByAttempts
            [
                [
                    ['open', ['no matter']],
                    ['write', ['some data to write']],
                ],
                [
                    'stream_select' => function () {
                        return 1;
                    },
                    'fwrite' => function () {
                        return 0;
                    },
                    'stream_socket_sendto' => function ($handle, $data) {
                        return strlen($data);
                    }
                ],
                'Failed to send data.'
            ]
        ];
    }
    /**
     * Create SocketInterface implementation for test
     *
     * @return SocketInterface
     */
    protected function createSocketInterface()
    {
        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [ ],
            '',
            true,
            true,
            true,
            ['createSocketResource']
        );

        $mock->expects(self::any())->method('createSocketResource')->willReturnCallback(
            function () {
                return fopen('php://temp', 'rw');
            }
        );
        return $mock;
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket = $this->createSocketInterface();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
            function ($resource, $wantPeer) {
                return $resource === $this->socket->getStreamResource() ?
                    'temp' :
                    \stream_socket_get_name($resource, $wantPeer);
            }
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fread')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
    }
}
