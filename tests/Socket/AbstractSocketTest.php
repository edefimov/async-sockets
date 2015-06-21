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

        $this->socket->setBlocking(false);
        $this->socket->open('php://temp');
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
                $mock = $this->getMockForAbstractClass('AsyncSockets\Frame\FrameInterface');
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
