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

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Socket\Io\TcpServerIo;
use AsyncSockets\Socket\ServerSocket;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class TcpServerIoTest
 */
class TcpServerIoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * TcpServerIo
     *
     * @var TcpServerIo
     */
    private $object;

    /**
     * testExceptionWillBeThrownOnAcceptFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\AcceptException
     * @expectedExceptionMessage Can not accept client connection.
     */
    public function testExceptionWillBeThrownOnAcceptFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_accept');
        $mocker->setCallable(function () {
            return false;
        });

        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface')
        );
    }

    /**
     * testCantWriteToServerSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not write data to tcp server socket.
     */
    public function testCantWriteToServerSocket()
    {
        $this->object->write('data');
    }

    /**
     * testResponseStructureIsValid
     *
     * @return void
     */
    public function testResponseStructureIsValid()
    {
        $actualPeerName = '127.0.0.1:12345';
        $peerResource   = null;

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_accept');
        $mocker->setCallable(function ($socket, $timeout, &$peerName) use ($actualPeerName, &$peerResource) {
            $peerName     = $actualPeerName;
            $peerResource = fopen('php://temp', 'rw');
            return $peerResource;
        });

        $frame = $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface')
        );

        /** @var AcceptedFrame $frame */
        self::assertInstanceOf('AsyncSockets\Frame\AcceptedFrame', $frame, 'Invalid frame created');
        self::assertEquals($actualPeerName, (string) $frame, 'Invalid frame data');
        self::assertInstanceOf('AsyncSockets\Socket\AcceptedSocket', $frame->getClientSocket(), 'Invalid socket');
        self::assertNotNull($peerResource, 'Accept method was not called');
        $frame->getClientSocket()->open('');
        self::assertSame($peerResource, $frame->getClientSocket()->getStreamResource(), 'Unexpected resource.');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->object = new TcpServerIo(new ServerSocket());

        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->setCallable(
            function ($resource) {
                $data = \stream_get_meta_data($resource);
                $data['stream_type'] = 'tcp_socket';
                return $data;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server')->setCallable(
            function () {
                return fopen('php://temp', 'rw');
            }
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_accept')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->restoreNativeHandler();
    }
}
