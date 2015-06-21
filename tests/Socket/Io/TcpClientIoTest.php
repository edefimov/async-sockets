<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Socket\ClientSocket;
use AsyncSockets\Socket\Io\TcpClientIo;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class TcpClientIoTest
 */
class TcpClientIoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var TcpClientIo
     */
    private $object;

    /**
     * testCantReadOnClosedSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantReadOnClosedSocket()
    {
        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface')
        );
    }

    /**
     * testCantWriteInClosedSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantWriteInClosedSocket()
    {
        $this->object->write('data');
    }

    /**
     * testIoFailures
     *
     * @param array      $methodCalls Method calls on socket: [methodName, arguments]
     * @param callable[] $mocks PHP functions to mock
     * @param string     $exceptionMessage Exception message to test
     *
     * @return void
     * @throws \Exception
     * @dataProvider ioDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testIoFailures(array $methodCalls, array $mocks, $exceptionMessage = '')
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->setCallable(
            function () {
                return fopen('php://temp', 'rw');
            }
        );
        $this->setExpectedException('AsyncSockets\Exception\NetworkSocketException', $exceptionMessage);
        foreach ($mocks as $name => $callable) {
            PhpFunctionMocker::getPhpFunctionMocker($name)->setCallable($callable);
        }


        $socket = new ClientSocket();
        $object = new TcpClientIo($socket);
        $socket->open('no matter');
        foreach ($methodCalls as $methodCall) {
            call_user_func_array([$object, $methodCall[0]], $methodCall[1]);
        }
    }

    /**
     * ioDataProvider
     *
     * @return array
     */
    public function ioDataProvider()
    {
        $falseFunction = function () {
            return false;
        };

        $streamSocketMock = function () {
            return 'php://temp';
        };

        $picker = $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface');

        // data for testing read/write. Format:
        // method, arguments, mock functions
        return [
            // testExceptionWillBeThrownOnWriteError
            [
                [
                    ['write', ['some data to write']],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => $falseFunction,
                ],
                'Failed to send data.'
            ],

            // testWriteSocketSendToFail
            [
                [
                    ['write', ['some data to write']],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => function () {
                        return 1;
                    },
                    'stream_socket_sendto'   => function () {
                        return -1;
                    },
                ],
                'Failed to send data.'
            ],

            // testActualWritingFail
            [
                [
                    ['write', ['some data to write']],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => function () {
                        return 1;
                    },
                    'fwrite'                 => $falseFunction,
                    'stream_socket_sendto'   => function ($handle, $data) {
                        return strlen($data);
                    },
                ],
                'Failed to send data.'
            ],

            // testWriteFailByAttempts
            [
                [
                    ['write', ['some data to write']],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => function () {
                        return 1;
                    },
                    'fwrite'                 => function () {
                        return 0;
                    },
                    'stream_socket_sendto'   => function ($handle, $data) {
                        return strlen($data);
                    },
                ],
                'Failed to send data.'
            ],

            // testExceptionWillBeThrownOnReadError
            [
                [
                    ['read', [$picker]],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => $falseFunction,
                ],
                'Failed to read data.'
            ],

            // testActualReadingFail
            [
                [
                    ['read', [$picker]],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => function () {
                        return 1;
                    },
                    'fread'                  => $falseFunction,
                    'stream_socket_recvfrom' => function () {
                        return 'x';
                    },
                ],
                'Failed to read data.'
            ],

            // testLossConnectionOnReading
            [
                [
                    ['read', [$picker]],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => function () {
                        return 1;
                    },
                    'fread'                  => function () use ($falseFunction) {
                        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
                            $falseFunction
                        );

                        return '';
                    },
                    'stream_socket_recvfrom' => function () {
                        return 'x';
                    },
                ],
                'Remote connection has been lost.'
            ],

            // testConnectionRefusedOnRead
            [
                [
                    ['read', [$picker]],
                ],
                [
                    'stream_socket_get_name' => $falseFunction
                ],
                'Connection refused.'
            ],

            // testConnectionRefusedOnWrite
            [
                [
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
                    ['write', ['some data to write']],
                ],
                [
                    'stream_socket_get_name' => $streamSocketMock,
                    'stream_select'          => function () {
                        return 1;
                    },
                    'fwrite'                 => function () use ($falseFunction) {
                        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
                            $falseFunction
                        );

                        return 0;
                    },
                    'stream_socket_sendto'   => function ($handle, $data) {
                        return strlen($data);
                    },
                ],
                'Remote connection has been lost.'
            ],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->object = new TcpClientIo(new ClientSocket());
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->setCallable(
            function ($resource) {
                $data = \stream_get_meta_data($resource);
                $data['stream_type'] = 'tcp_socket';
                return $data;
            }
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->restoreNativeHandler();
    }
}
