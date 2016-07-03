<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Exception\ConnectionException;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class AbstractSocketTest
 *
 * @SuppressWarnings("unused")
 * @SuppressWarnings("TooManyMethods")
 */
class AbstractSocketTest extends AbstractTestCase
{
    /**
     * SocketInterface
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * testStringCastingForClosedSocket
     *
     * @return void
     */
    public function testStringCastingForClosedSocket()
    {
        self::assertSame('[closed socket]', (string) $this->socket, 'Incorrect string casting for closed socket');
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
                $data = \stream_get_meta_data($resource) + [ 'blocked' => true ];
                $data['stream_type'] = 'tcp_socket';
                return $data;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking')->setCallable(
            function ($resource, $isBlocking) {
                self::assertEquals(false, $isBlocking, 'Unexpected value passed');
                return \stream_set_blocking($resource, $isBlocking);
            }
        );

        $this->socket->open('php://temp');
        self::assertRegExp(
            '#\[\#\d+, php://temp\]#',
            (string) $this->socket,
            'Incorrect string casting for opened socket'
        );
    }

    /**
     * testSystemStreamSettingsChange
     *
     * @return void
     */
    public function testSystemStreamSettingsChange()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_set_timeout')->setCallable(
            function ($socket, $seconds, $microseconds = 0) {
                self::assertSame($this->socket->getStreamResource(), $socket, 'Unexpected socket resource');
                self::assertSame(0, $seconds, 'Seconds must be set to zero on socket initialization');
                self::assertSame(0, $microseconds, 'Microseconds must be set to zero on socket initialization');
            }
        );

        $this->socket->open('php://temp');
    }

    /**
     * testCantReadFromClosedSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantReadFromClosedSocket()
    {
        $this->socket->read($this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface'));
    }

    /**
     * testCantWriteToClosedSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantWriteToClosedSocket()
    {
        $this->socket->write('test');
    }

    /**
     * testThatConnectionExceptionChangesConnectState
     *
     * @param string $method Method name: read or write
     * @param mixed  $argument Method argument
     *
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     * @dataProvider connectionExceptionChangesConnectStateDataProvider
     */
    public function testThatConnectionExceptionChangesConnectState($method, $argument = null)
    {
        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\Io\IoInterface',
            [],
            '',
            true,
            true,
            true,
            [$method]
        );

        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [ ],
            '',
            true,
            true,
            true,
            ['createSocketResource']
        );


        $socket->expects(self::any())->method('createSocketResource')->willReturnCallback(
            function () {
                return fopen('php://temp', 'rw');
            }
        );

        $mock->expects(self::any())->method($method)->willThrowException(new ConnectionException($socket));

        $socket->expects(self::any())->method('createIoInterface')->willReturn($mock);
        try {
            /** @var SocketInterface $socket */
            $socket->open('no matter');
            $socket->{$method}($argument);
        } catch (ConnectionException $e) {
            $socket->{$method}($argument);
        }
    }

    /**
     * testThatFailedSocketResourceAllocationThrowsException
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\ConnectionException
     * @expectedExceptionMessage Can not allocate socket resource.
     */
    public function testThatFailedSocketResourceAllocationThrowsException()
    {
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [ ],
            '',
            true,
            true,
            true,
            ['createSocketResource']
        );


        $socket->expects(self::any())->method('createSocketResource')->willReturn(false);
        $socket->open('no matter');
    }

    /**
     * testExceptionWillBeThrownIfSocketTypeIsUnknown
     *
     * @return void
     */
    public function testExceptionWillBeThrownIfSocketTypeIsUnknown()
    {
        if (!($this->socket instanceof \PHPUnit_Framework_MockObject_MockObject)) {
            $this->setExpectedExceptionRegExp('\LogicException');

            PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->setCallable(function () {
                return [];
            });

            $this->socket->open('no matter');
        }
    }

    /**
     * connectionExceptionChangesConnectStateDataProvider
     *
     * @return array
     */
    public function connectionExceptionChangesConnectStateDataProvider()
    {

        return [
            ['read', $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface')],
            ['write', null],
        ];
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
        PhpFunctionMocker::getPhpFunctionMocker('stream_set_timeout')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
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
}
