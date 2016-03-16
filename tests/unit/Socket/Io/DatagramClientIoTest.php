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

use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\Io\AbstractIo;
use AsyncSockets\Socket\Io\DatagramClientIo;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class DatagramClientIoTest
 */
class DatagramClientIoTest extends AbstractClientIoTest
{
    /**
     * Remote address for socket
     *
     * @var string
     */
    protected $address;

    /** {@inheritdoc} */
    protected function createIoInterface(SocketInterface $socket)
    {
        return new DatagramClientIo($socket, '127.0.0.1:4325');
    }

    /** {@inheritdoc} */
    protected function setConnectedStateForTestObject($isConnected)
    {
        // nothing is required
    }

    /**
     * testReadBasicDatagram
     *
     * @param string|null $remoteAddress Remote address for I/O object
     *
     * @return void
     * @dataProvider remoteAddressDataProvider
     */
    public function testReadBasicDatagram($remoteAddress)
    {
        $this->setUpIoObject($remoteAddress);

        $expectedData = $data = md5(microtime(true));
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function ($handle, $size, $flags, &$address) use (&$data) {
                $address = $this->address;
                $result  = $data;

                if (!($flags & STREAM_PEEK)) {
                    $data = '';
                }
                return $result;
            }
        );

        $frame = $this->object->read(new RawFramePicker());
        self::assertEquals($expectedData, (string) $frame, 'Incorrect frame');
    }

    /**
     * testReadHugeDatagram
     *
     * @param string|null $remoteAddress Remote address for I/O object
     *
     * @return void
     * @dataProvider remoteAddressDataProvider
     */
    public function testReadHugeDatagram($remoteAddress)
    {
        $this->setUpIoObject($remoteAddress);

        $expectedData  = str_repeat('1', AbstractIo::SOCKET_BUFFER_SIZE);
        $expectedData .= str_repeat('2', AbstractIo::SOCKET_BUFFER_SIZE);
        $expectedData .= str_repeat('3', AbstractIo::SOCKET_BUFFER_SIZE);
        $data          = $expectedData;
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function ($handle, $size, $flags, &$address) use (&$data) {
                $address = $this->address;
                $result  = $data;

                if (!($flags & STREAM_PEEK)) {
                    $data = '';
                }
                return substr($result, 0, $size);
            }
        );

        $frame = $this->object->read(new RawFramePicker());
        self::assertEquals($expectedData, (string) $frame, 'Incorrect frame');
    }

    /**
     * testReadUnreachedFrame
     *
     * @param string|null $remoteAddress Remote address for I/O object
     *
     * @return void
     * @dataProvider remoteAddressDataProvider
     * @expectedException \AsyncSockets\Exception\FrameSocketException
     */
    public function testReadUnreachedFrame($remoteAddress)
    {
        $this->setUpIoObject($remoteAddress);

        $data   = '1';
        $picker = $this->getMockForAbstractClass(
            'AsyncSockets\Frame\FramePickerInterface',
            [],
            '',
            true,
            true,
            true,
            ['isEof']
        );
        $picker->expects(self::any())->method('isEof')->willReturn(false);

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function ($handle, $size, $flags, &$address) use (&$data) {
                $address = $this->address;
                $result  = $data;

                if (!($flags & STREAM_PEEK)) {
                    $data = '';
                }
                return $result;
            }
        );

        $this->object->read($picker);
    }

    /**
     * testWriteData
     *
     * @param string|null $remoteAddress Remote address for I/O object
     *
     * @return void
     * @dataProvider remoteAddressDataProvider
     */
    public function testWriteData($remoteAddress)
    {
        $this->setUpIoObject($remoteAddress);

        $data = md5(microtime(true));
        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::once())->method('count');
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable(
            function ($handle, $actualData, $flags, $address) use (&$data, $mock) {
                self::assertEquals($this->address, $address, 'Incorrect destination address');
                self::assertEquals($data, $actualData, 'Incorrect data');
                /** @var \Countable $mock */
                $mock->count();
                return strlen($actualData);
            }
        );

        $this->object->write($data);
    }

    /**
     * remoteAddressDataProvider
     *
     * @return array
     */
    public function remoteAddressDataProvider()
    {
        return [
            [$this->randomIpAddress()],

            [null]
        ];
    }

    /**
     * setUpIoObject
     *
     * @param string|null $remoteAddress Remote address for I/O object
     *
     * @return void
     */
    protected function setUpIoObject($remoteAddress)
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
        $this->address = $remoteAddress;
        $socket->expects(self::any())->method('getStreamResource')->willReturn(fopen('php://temp', 'rw'));
        $this->object = new DatagramClientIo($socket, $this->address);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->setCallable(
            function ($resource) {
                $data = \stream_get_meta_data($resource);
                $data['stream_type'] = 'udp_socket';
                return $data;
            }
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->restoreNativeHandler();
    }
}
