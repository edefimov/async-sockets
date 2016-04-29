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

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Socket\Io\DatagramServerIo;
use AsyncSockets\Socket\SocketInterface;
use AsyncSockets\Socket\UdpClientSocket;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class DatagramServerIoTest
 */
class DatagramServerIoTest extends AbstractServerIoTest
{
    /**
     * Remote address
     *
     * @var string
     */
    private $remoteAddress;

    /**
     * Datagram for client
     *
     * @var string
     */
    private $data;

    /** {@inheritdoc} */
    protected function createIoInterface(SocketInterface $socket)
    {
        return new DatagramServerIo($socket, false);
    }

    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            true,
            true,
            true,
            ['getStreamResource']
        );
        $socket->expects(self::any())->method('getStreamResource')->willReturn(fopen('php://temp', 'r+'));
        /** @var SocketInterface $socket */
        return new UdpClientSocket(
            $socket,
            $this->remoteAddress,
            $this->data
        );
    }

    /**
     * prepareForTestResponseStructureIsValid
     *
     * @param string   &$remoteAddress Remote socket address
     * @param resource &$remoteResource Remote socket resource
     *
     * @return void
     */
    protected function prepareForTestResponseStructureIsValid(&$remoteAddress, &$remoteResource)
    {
        $remoteAddress  = $this->remoteAddress;
        $remoteResource = $this->socket->getStreamResource();
        $data           = $this->data;
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function ($resource, $size, $flags, &$address) use ($remoteAddress, &$data) {
                $address = $this->remoteAddress;
                $result  = $data;
                if (!($flags & STREAM_PEEK)) {
                    $data = '';
                }
                return $result;
            }
        );
    }

    /** {@inheritdoc} */
    public function testResponseStructureIsValid()
    {
        $frame = parent::testResponseStructureIsValid();
        self::assertInstanceOf('AsyncSockets\Socket\UdpClientSocket', $frame->getClientSocket(), 'Invalid socket');
    }

    /**
     * testIfRemoteAddressIsUnknownExceptionWillBeThrown
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\AcceptException
     */
    public function testIfRemoteAddressIsUnknownExceptionWillBeThrown()
    {
        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::once())->method('count');
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function ($resource, $size, $flags, &$address) use ($mock) {
                $address = '';
                $result  = $this->data;
                if (!($flags & STREAM_PEEK)) {
                    /** @var \Countable $mock */
                    $mock->count();
                    $this->data = '';
                }
                return $result;
            }
        );

        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface'),
            $this->context,
            false
        );
    }

    /**
     * testThatLocalSocketWorksWithoutAddress
     *
     * @return void
     */
    public function testThatLocalSocketWorksWithoutAddress()
    {
        $socket = $this->createSocketInterface();
        $object = new DatagramServerIo($socket, true);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function ($resource, $size, $flags, &$address) {
                $address = '';
                $result  = $this->data;
                if (!($flags & STREAM_PEEK)) {
                    $this->data = '';
                }
                return $result;
            }
        );

        $frame = $object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface'),
            $this->context,
            false
        );

        /** @var AcceptedFrame $frame */
        self::assertInstanceOf('AsyncSockets\Frame\AcceptedFrame', $frame, 'Invalid frame created');
        $frame->getClientSocket()->open('');
        self::assertSame(
            $socket->getStreamResource(),
            $frame->getClientSocket()->getStreamResource(),
            'Unexpected resource.'
        );
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->remoteAddress = $this->randomIpAddress();
        $this->data          = sha1(microtime(true));
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
    }
}
