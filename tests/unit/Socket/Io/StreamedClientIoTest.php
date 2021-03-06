<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Frame\FixedLengthFramePicker;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\ClientSocket;
use AsyncSockets\Socket\Io\Context;
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
        return new StreamedClientIo($socket, 0);
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
        $object = new StreamedClientIo($socket, 0);
        $socket->open('no matter');
        foreach ($methodCalls as $methodCall) {
            call_user_func_array([$object, $methodCall[0]], $methodCall[1]);
        }
    }

    /**
     * testConnectionRefusedOnRead
     *
     * @param bool $isOutOfBand Flag if data are out of band
     *
     * @return void
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\ConnectionException
     * @expectedExceptionMessage Connection refused.
     */
    public function testConnectionRefusedOnRead($isOutOfBand)
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(false);
        $this->ensureSocketIsOpened();
        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface'),
            $this->context,
            $isOutOfBand
        );
    }

    /**
     * testConnectionRefusedOnWrite
     *
     * @param bool $isOutOfBand Flag if data are out of band
     *
     * @return void
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\ConnectionException
     * @expectedExceptionMessage Connection refused.
     */
    public function testConnectionRefusedOnWrite($isOutOfBand)
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(false);
        $this->ensureSocketIsOpened();
        $this->object->write('data', $this->context, $isOutOfBand);
    }

    /**
     * testThatIfDataInSocketNotReadyForReadThenTheyWillBeReadLater
     *
     * @return void
     */
    public function testThatIfDataInSocketNotReadyForReadThenTheyWillBeReadLater()
    {
        $mockFread                = $this->getMockBuilder('Countable')->setMethods(['count'])
                                            ->getMockForAbstractClass();
        $mockStreamSocketRecvFrom = $this->getMockBuilder('Countable')->setMethods(['count'])
                                            ->getMockForAbstractClass();

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
            $frame = $this->object->read($picker, $this->context, false);
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
        $picker = $this->getMockBuilder('AsyncSockets\Frame\FramePickerInterface')
                        ->setMethods(['isEof', 'pickUpData', 'createFrame'])
                        ->getMockForAbstractClass();

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
            $this->object->read($picker, $this->context, false);
        }
    }

    /**
     * testExceptionWillBeThrownIfConnectionLost
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\DisconnectException
     */
    public function testExceptionWillBeThrownIfConnectionLost()
    {
        /** @var FramePickerInterface|\PHPUnit_Framework_MockObject_MockObject $picker */
        $picker = $this->getMockBuilder('AsyncSockets\Frame\FramePickerInterface')
                        ->setMethods(['isEof', 'pickUpData', 'createFrame'])
                        ->getMockForAbstractClass();

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
        $mock = $this->getMockBuilder('Countable')
            ->setMethods(['count'])
            ->getMockForAbstractClass();
        $mock->expects(self::any())
            ->method('count')
            ->willReturnOnConsecutiveCalls(false);


        for ($i = 0; $i < StreamedClientIo::READ_ATTEMPTS; $i++) {
            if ($i === StreamedClientIo::READ_ATTEMPTS-1) {
                PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')
                                 ->setCallable([$mock, 'count']);
            }

            $this->object->read($picker, $this->context, false);
        }
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
        $frame  = $this->object->read($picker, $this->context, false);

        self::assertEquals(
            implode('', array_slice($sequence, 0, $length)),
            (string) $frame,
            'Incorrect frame'
        );
        self::assertEquals($length, $idxSequence, 'Read too much bytes');
    }

    /**
     * testOobWriting
     *
     * @return void
     */
    public function testOobWriting()
    {
        $length = mt_rand(1, 100);
        $data   = md5(microtime());
        while (strlen($data) < $length) {
            $data .= md5($data);
        }
        $data = substr($data, 0, $length);

        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();
        $object = new StreamedClientIo($this->socket, $length);

        $expectation = $this->getMockBuilder('Countable')
            ->setMethods(['count'])
            ->getMockForAbstractClass();

        $hasSentAnything = false;
        $expectation->expects(self::any())
            ->method('count')
            ->willReturnCallback(function($resource, $data, $flags = null) use (&$hasSentAnything) {
                if ($data) {
                    self::assertTrue((bool) ($flags & STREAM_OOB), 'Oob flag is not set');
                    $hasSentAnything = true;
                }
                return strlen($data);
            });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable([ $expectation, 'count' ]);
        $object->write($data, $this->context, true);
        self::assertTrue($hasSentAnything, 'Data were not actually sent');
    }

    /**
     * testOobWritingError
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\SendDataException
     */
    public function testOobWritingError()
    {
        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();
        $object = new StreamedClientIo($this->socket, 1);

        $expectation = $this->getMockBuilder('Countable')
            ->setMethods(['count'])
            ->getMockForAbstractClass();

        $expectation->expects(self::any())
            ->method('count')
            ->willReturnOnConsecutiveCalls(0, -1);

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable([ $expectation, 'count' ]);
        $object->write('x', $this->context, true);
    }

    /**
     * testOobReading
     *
     * @return void
     */
    public function testOobReading()
    {
        $length = mt_rand(1, 100);
        $data   = md5(microtime());
        while (strlen($data) < $length) {
            $data .= md5($data);
        }
        $data = substr($data, 0, $length);

        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();
        $object = new StreamedClientIo($this->socket, $length);

        $expectation = $this->getMockBuilder('Countable')
                            ->setMethods(['count'])
                            ->getMockForAbstractClass();

        $expectation->expects(self::once())
                    ->method('count')
                    ->willReturnCallback(function($resource, $length, $flags = null) use ($data) {
                        self::assertTrue((bool) ($flags & STREAM_OOB), 'Oob flag is not set');

                        return $data;
                    });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable([ $expectation, 'count' ]);
        $frame = $object->read(new RawFramePicker(), $this->context, true);
        self::assertSame($data, (string) $frame, 'Data were read incorrect');
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
                    ['write', ['some data to write', new Context(), false]],
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
                    ['read', [$picker, new Context(), false]],
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
                    ['write', ['some data to write', new Context(), false]],
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
