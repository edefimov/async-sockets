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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\Io\StreamedServerIo;
use AsyncSockets\Socket\ServerSocket;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class StreamedServerIoTest
 */
class StreamedServerIoTest extends AbstractServerIoTest
{
    /** {@inheritdoc} */
    protected function createIoInterface(SocketInterface $socket)
    {
        return new StreamedServerIo($socket);
    }

    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        return new ServerSocket();
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

        $picker = $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface');
        /** @var FramePickerInterface $picker */
        $this->object->read($picker, $this->context, false);
    }

    /** {@inheritdoc} */
    public function testResponseStructureIsValid()
    {
        $frame = parent::testResponseStructureIsValid();
        self::assertInstanceOf('AsyncSockets\Socket\AcceptedSocket', $frame->getClientSocket(), 'Invalid socket');
    }

    /** {@inheritdoc} */
    protected function prepareForTestResponseStructureIsValid(&$remoteAddress, &$remoteResource)
    {
        $remoteAddress  = '127.0.0.1:12345';
        $remoteResource = null;

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_accept');
        $mocker->setCallable(function ($socket, $timeout, &$peerName) use ($remoteAddress, &$remoteResource) {
            $peerName       = $remoteAddress;
            $remoteResource = fopen('php://temp', 'rw');
            return $remoteResource;
        });
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
