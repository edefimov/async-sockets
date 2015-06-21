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

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Socket\ServerSocket;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class ServerSocketTest
 */
class ServerSocketTest extends AbstractSocketTest
{
    /**
     * testExceptionWillBeThrowsOnCreateFailWithErrNo
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionCode 500
     * @expectedExceptionMessage Tested socket creation
     */
    public function testExceptionWillBeThrowsOnCreateFailWithErrNo()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server');
        $mocker->setCallable(function ($remoteSocket, &$errno, &$errstr) {
            self::assertEquals('php://temp', $remoteSocket, 'Incorrect address passed to stream_socket_server');
            $errno  = 500;
            $errstr = 'Tested socket creation';
            return false;
        });

        $this->socket->open('php://temp');
    }

    /**
     * testExceptionWillBeThrowsOnCreateFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionWillBeThrowsOnCreateFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server');
        $mocker->setCallable(function ($remoteSocket, &$errno, &$errstr) {
            $errno  = 0;
            $errstr = '';
            return false;
        });

        $this->socket->open('php://temp');
    }

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

        $this->socket->open('php://temp');
        $this->socket->read();
    }

    /**
     * testResponseStructureIsValid
     *
     * @return void
     */
    public function testResponseStructureIsValid()
    {
        $actualPeerName = '127.0.0.1:12345';

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_accept');
        $mocker->setCallable(function ($socket, $timeout, &$peerName) use ($actualPeerName) {
            $peerName = $actualPeerName;
            return fopen('php://temp', 'rw');
        });

        $this->socket->open('php://temp');
        $frame = $this->socket->read();

        /** @var AcceptedFrame $frame */
        self::assertInstanceOf('AsyncSockets\Frame\AcceptedFrame', $frame, 'Invalid frame created');
        self::assertEquals($actualPeerName, (string) $frame, 'Invalid frame data');
        self::assertInstanceOf('AsyncSockets\Socket\AcceptedSocket', $frame->getClientSocket(), 'Invalid socket');
    }

    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        return new ServerSocket();
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server');
        $mocker->setCallable(function () {
            return fopen('php://temp', 'rw');
        });
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_accept')->restoreNativeHandler();
    }
}
