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
        self::assertInstanceOf(
            'AsyncSockets\Socket\SocketResponseInterface',
            $this->socket->read(),
            'Strange response'
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
            ->disableOriginalConstructor()
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
        $falseFunction = function () {
            return false;
        };

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

            // testWriteFailureIfNoResource
            [
                [
                    ['write', ['no matter']],
                ],
                [

                ],
                'Can not start io operation on uninitialized socket.'
            ],

            // testExceptionWillBeThrownOnWriteError
            [
                [
                    ['open', ['no matter']],
                    ['write', ['some data to write']],
                ],
                [
                    'stream_select' => $falseFunction,
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
                    'fwrite' => $falseFunction,
                    'stream_socket_sendto' => function ($handle, $data) {
                        return strlen($data);
                    }
                ],
                'Failed to send data.'
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
            ['createSocketResource', 'doReadData', 'isConnected']
        );

        $mock->expects(self::any())->method('isConnected')->willReturn(true);
        $mock->expects(self::any())->method('createSocketResource')->willReturnCallback(
            function () {
                return fopen('php://temp', 'rw');
            }
        );

        $mock->expects(self::any())->method('doReadData')->willReturnCallback(
            function () {
                $mock = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketResponseInterface');
                return $mock;
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
