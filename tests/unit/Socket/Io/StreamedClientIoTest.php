<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Frame\FixedLengthFramePicker;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\ClientSocket;
use AsyncSockets\Socket\Io\StreamedClientIo;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class StreamedClientIoTest
 */
class StreamedClientIoTest extends AbstractClientIoTest
{
    /** {@inheritdoc} */
    protected function createIoInterface(SocketInterface $socket)
    {
        return new StreamedClientIo($socket);
    }

    /** {@inheritdoc} */
    protected function setConnectedStateForTestObject($isConnected)
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
            function () use ($isConnected) {
                return $isConnected;
            }
        );
    }

    /**
     * prepareForTestWriteFailureWithAttempts
     *
     * @return void
     */
    protected function prepareForTestWriteFailureWithAttempts()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return 1;
            }
        );
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->setCallable(
            function () {
                return 0;
            }
        );
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable(
            function () {
                return 0;
            }
        );
    }

    /**
     * prepareForTestExceptionWillBeThrownOnWriteError
     *
     * @return void
     */
    protected function prepareForTestExceptionWillBeThrownOnWriteError()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return false;
            }
        );
    }

    /**
     * prepareForTestWriteSocketSendToFail
     *
     * @return void
     */
    protected function prepareForTestWriteSocketSendToFail()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return 1;
            }
        );
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
        $object = new StreamedClientIo($socket);
        $socket->open('no matter');
        foreach ($methodCalls as $methodCall) {
            call_user_func_array([$object, $methodCall[0]], $methodCall[1]);
        }
    }

    /**
     * testConnectionRefusedOnRead
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\ConnectionException
     * @expectedExceptionMessage Connection refused.
     */
    public function testConnectionRefusedOnRead()
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(false);
        $this->ensureSocketIsOpened();
        $this->object->read($this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface'));
    }

    /**
     * testConnectionRefusedOnWrite
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\ConnectionException
     * @expectedExceptionMessage Connection refused.
     */
    public function testConnectionRefusedOnWrite()
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(false);
        $this->ensureSocketIsOpened();
        $this->object->read(new RawFramePicker());
    }

    /**
     * testThatIfDataInSocketNotReadyForReadThenTheyWillBeReadLater
     *
     * @return void
     */
    public function testThatIfDataInSocketNotReadyForReadThenTheyWillBeReadLater()
    {
        $mockFread                = $this->getMock('Countable', ['count']);
        $mockStreamSocketRecvFrom = $this->getMock('Countable', ['count']);

        $mockFread->expects(self::any())->method('count')
            ->willReturnOnConsecutiveCalls('1', '', '', '2', '', '');

        $mockStreamSocketRecvFrom->expects(self::any())->method('count')
            ->willReturnOnConsecutiveCalls(false, '2', '');

        PhpFunctionMocker::getPhpFunctionMocker('fread')->setCallable([$mockFread, 'count']);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            [ $mockStreamSocketRecvFrom, 'count' ]
        );

        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();

        $picker = new FixedLengthFramePicker(2);
        for ($i = 0; $i < 3; $i++) {
            $frame = $this->object->read($picker);
        }

        self::assertEquals('12', (string) $frame, 'Incorrect frame');
    }

    /**
     * testExceptionWillBeThrownIfFrameNotCollected
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\FrameException
     */
    public function testExceptionWillBeThrownIfFrameNotCollected()
    {
        /** @var FramePickerInterface|\PHPUnit_Framework_MockObject_MockObject $picker */
        $picker = $this->getMock(
            'AsyncSockets\Frame\FramePickerInterface',
            ['isEof', 'pickUpData', 'createFrame']
        );

        $picker->expects(self::any())->method('isEof')->willReturn(false);
        $picker->expects(self::any())->method('pickUpData')->willReturnCallback(function ($data) {
            return $data;
        });
        $picker->expects(self::any())->method('createFrame')->willReturnCallback(function () {
            return $this->getMockForAbstractClass('AsyncSockets\Frame\FrameInterface');
        });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return false;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('fread')->setCallable(
            function () {
                return '';
            }
        );

        $this->ensureSocketIsOpened();
        $this->setConnectedStateForTestObject(true);
        for ($i = 0; $i < StreamedClientIo::READ_ATTEMPTS; $i++) {
            $this->object->read($picker);
        }

        self::fail('Exception was not thrown');
    }

    /**
     * testReadInfinityStream
     *
     * @return void
     */
    public function testReadInfinityStream()
    {
        $alphabet       = str_split('0123456789');
        $sequence       = [ ];
        $sequenceLength = 2048;
        $idxSequence    = 0;
        for ($i = 0; $i < $sequenceLength; $i++) {
            $idx        = mt_rand(0, count($alphabet)-1);
            $sequence[] = $alphabet[$idx];
        }
        PhpFunctionMocker::getPhpFunctionMocker('fread')->setCallable(
            function () use (&$sequence, &$idxSequence) {
                if ($idxSequence + 1 >= count($sequence)) {
                    $idxSequence = 0;
                }

                return $sequence[$idxSequence++];
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return 'x';
            }
        );

        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();

        $length = 12;
        $picker = new FixedLengthFramePicker($length);
        $frame  = $this->object->read($picker);

        self::assertEquals(
            implode('', array_slice($sequence, 0, $length)),
            (string) $frame,
            'Incorrect frame'
        );
        self::assertEquals($length, $idxSequence, 'Read too much bytes');
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
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fread')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
    }
}
