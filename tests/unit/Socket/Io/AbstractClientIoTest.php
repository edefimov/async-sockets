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

use AsyncSockets\Socket\Io\AbstractClientIo;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class AbstractClientIoTest
 */
class AbstractClientIoTest extends AbstractIoTest
{
    /**
     * testWriteFailureWithAttempts
     *
     * @return void
     */
    public function testWriteFailureWithAttempts()
    {
        $this->prepareFor(__FUNCTION__);
        $this->ensureSocketIsOpened();
        $this->setExpectedException('AsyncSockets\Exception\SendDataException', 'Failed to send data.');
        $this->setConnectedStateForTestObject(true);
        for ($i = 0; $i < AbstractClientIo::IO_ATTEMPTS; $i++) {
            $this->object->write('something', $this->context, false);
        }
    }

    /**
     * ensureSocketIsOpened
     *
     * @return void
     */
    protected function ensureSocketIsOpened()
    {
        $this->socket->expects(self::any())->method('getStreamResource')->willReturn(fopen('php://temp', 'r+'));
    }

    /**
     * Set up connected state for socket object
     *
     * @param bool $isConnected True, if connected state is required, false - disconnected
     *
     * @return void
     */
    protected function setConnectedStateForTestObject($isConnected)
    {
        $object = $this->object;
        /** @var \PHPUnit_Framework_MockObject_MockObject $object */
        $object->expects(self::any())->method('isConnected')->willReturn($isConnected);
    }

    /**
     * testCantReadOnClosedSocket
     *
     * @param bool $isOutOfBand Is out of band
     *
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantReadOnClosedSocket($isOutOfBand)
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(false);
        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface'),
            $this->context,
            $isOutOfBand
        );
    }

    /**
     * testExceptionWillBeThrownOnWriteError
     *
     * @param bool $isOutOfBand Flag if data are out of band
     *
     * @return void
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testExceptionWillBeThrownOnWriteError($isOutOfBand)
    {
        $this->prepareFor(__FUNCTION__);
        $this->object->write('data', $this->context, $isOutOfBand);
    }

    /**
     * testCantWriteInClosedSocket
     *
     * @param bool $isOutOfBand Flag if data are out of band
     *
     * @return void
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantWriteInClosedSocket($isOutOfBand)
    {
        $this->setConnectedStateForTestObject(false);
        $this->object->write('data', $this->context, $isOutOfBand);
    }

    /**
     * testWriteSocketSendToFail
     *
     * @return void
     */
    public function testWriteSocketSendToFail()
    {
        $hasWriteMethod = get_class() !== get_called_class();
        if (!$hasWriteMethod) {
            return;
        }

        $this->setExpectedException(
            'AsyncSockets\Exception\SendDataException',
            'Failed to send data.'
        );

        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(true);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable(
            function () {
                return -1;
            }
        );
        $this->ensureSocketIsOpened();
        $this->object->write('data', $this->context, false);
    }

    /**
     * testExceptionIsThrownWhenOobWriteIsUnsupported
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\UnsupportedOperationException
     */
    public function testExceptionIsThrownWhenOobWriteIsUnsupported()
    {
        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();

        $disableOobWriting = \Closure::bind(
            function () {
                /** @var AbstractClientIo $this */
                $this->maxOobPacketLength = 0;
            },
            $this->object,
            'AsyncSockets\Socket\Io\AbstractClientIo'
        );
        $disableOobWriting();

        $this->object->write('something', $this->context, true);
    }

    /**
     * testExceptionIsThrownWhenOobDataLengthMoreThanPacketSize
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\UnsupportedOperationException
     */
    public function testExceptionIsThrownWhenOobDataLengthMoreThanPacketSize()
    {
        $length = mt_rand(10, 100);
        $data   = md5(microtime());
        while (strlen($data) < $length) {
            $data .= md5($data);
        }
        $data = substr($data, 0, $length);

        $this->setConnectedStateForTestObject(true);
        $this->ensureSocketIsOpened();

        $setOobPacketLength = \Closure::bind(
            function () use ($length) {
                /** @var AbstractClientIo $this */
                $this->maxOobPacketLength = (int) floor($length / 2);
            },
            $this->object,
            'AsyncSockets\Socket\Io\AbstractClientIo'
        );
        $setOobPacketLength();

        $this->object->write($data, $this->context, true);
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

        return $socket;
    }

    /** {@inheritdoc} */
    protected function createIoInterface(SocketInterface $socket)
    {
        $object = $this->getMockBuilder('AsyncSockets\Socket\Io\AbstractClientIo')
                    ->setConstructorArgs([$socket, 0])
                    ->setMethods(['isConnected'])
                    ->enableProxyingToOriginalMethods()
                    ->getMockForAbstractClass();

        return $object;
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
    }
}
