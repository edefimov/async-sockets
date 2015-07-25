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

use AsyncSockets\Frame\NullFramePicker;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class AbstractClientIoTest
 */
class AbstractClientIoTest extends AbstractIoTest
{
    /**
     * Make some preparations for test method
     *
     * @param string $testMethod Test method from this class
     *
     * @return void
     */
    protected function prepareFor($testMethod)
    {
        $method = 'prepareFor' . ucfirst($testMethod);
        if (method_exists($this, $method)) {
            $this->$method();
        }
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
        $object = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\Io\AbstractClientIo',
            [$socket],
            '',
            true,
            true,
            true,
            ['isConnected']
        );

        return $object;
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
     * testWriteFailureWithAttempts
     *
     * @return void
     */
    public function testWriteFailureWithAttempts()
    {
        $this->prepareFor(__FUNCTION__);
        $this->ensureSocketIsOpened();
        $this->setExpectedException('AsyncSockets\Exception\NetworkSocketException', 'Failed to send data.');
        $this->setConnectedStateForTestObject(true);
        $this->object->write('something');
    }

    /**
     * testCantReadOnClosedSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantReadOnClosedSocket()
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(false);
        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface')
        );
    }

    /**
     * testExceptionWillBeThrownOnWriteError
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testExceptionWillBeThrownOnWriteError()
    {
        $this->prepareFor(__FUNCTION__);
        $this->object->write('data');
    }

    /**
     * testCantWriteInClosedSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testCantWriteInClosedSocket()
    {
        $this->setConnectedStateForTestObject(false);
        $this->object->write('data');
    }

    /**
     * testWriteSocketSendToFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Failed to send data.
     */
    public function testWriteSocketSendToFail()
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(true);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable(
            function () {
                return -1;
            }
        );
        $this->ensureSocketIsOpened();
        $this->object->write('data');
    }

    /**
     * testThatIoWillNotTryWriteIfNotSelected
     *
     * @return void
     */
    public function testThatIoWillNotTryWriteIfNotSelected()
    {
        $this->prepareFor(__FUNCTION__);
        $this->setConnectedStateForTestObject(true);
        foreach (['stream_socket_sendto', 'fwrite'] as $f) {
            PhpFunctionMocker::getPhpFunctionMocker($f)->setCallable(
                function () use ($f) {
                    self::fail("{$f} shouldn't be called");
                }
            );
        }

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(function (array &$read = null, array &$write = null) {
            $read  = [];
            $write = [];
            return 0;
        });

        $this->ensureSocketIsOpened();
        $this->object->write('data');
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
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
}
