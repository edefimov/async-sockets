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

use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\SocketInterface;
use AsyncSockets\Socket\UdpClientSocket;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class UdpClientSocketTest
 */
class UdpClientSocketTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var UdpClientSocket
     */
    protected $object;

    /**
     * Origin socket for test object
     *
     * @var SocketInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $socket;

    /**
     * Socket return data
     *
     * @var string
     */
    protected $data;

    /**
     * Remote address
     *
     * @var string
     */
    protected $remoteAddress;

    /**
     * Create mock for test object
     *
     * @return SocketInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createSocketInterface()
    {
        $object = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            true,
            true,
            true,
            ['open', 'close', 'getStreamResource', '__toString']
        );

        return $object;
    }

    /**
     * testOpenNotPassToOrigin
     *
     * @return void
     */
    public function testOpenNotPassToOrigin()
    {
        $this->socket->expects(self::never())->method('open');
        $this->object->open('');
    }

    /**
     * testCloseNotPassToOrigin
     *
     * @return void
     */
    public function testCloseNotPassToOrigin()
    {
        $this->socket->expects(self::never())->method('close');
        $this->object->close();
    }

    /**
     * testStringCastingAsOrigin
     *
     * @return void
     */
    public function testStringCastingAsOrigin()
    {
        $value = sha1(microtime(true));
        $this->socket->expects(self::once())
            ->method('__toString')
            ->willReturn($value);
        self::assertSame(
            $value,
            $this->object->__toString(),
            'Incorrect string returned'
        );
    }

    /**
     * testGetStreamResource
     *
     * @return void
     */
    public function testGetStreamResource()
    {
        $testValue   = mt_rand(1, PHP_INT_MAX);
        $this->socket->expects(self::once())
            ->method('getStreamResource')
            ->willReturn($testValue);
        $actualValue = $this->object->getStreamResource();
        self::assertSame($testValue, $actualValue, 'Unexpected resource value');
    }

    /**
     * testWrite
     *
     * @return void
     * @covers \AsyncSockets\Socket\UdpClientSocket::write
     */
    public function testWrite()
    {
        $testValue   = fopen('php://temp', 'r+');
        $this->socket->expects(self::any())
                     ->method('getStreamResource')
                     ->willReturn($testValue);

        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::once())->method('count')->with();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable(
            function ($resource, $data, $flags, $address) use ($mock, $testValue) {
                /** @var \Countable $mock */
                $mock->count();
                self::assertSame($testValue, $resource, 'Incorrect resource');
                self::assertSame($this->data, $data, 'Incorrect data');
                self::assertSame($this->remoteAddress, $address, 'Incorrect address');
                return strlen($data);
            }
        );

        $this->object->write($this->data);
        fclose($testValue);
    }

    /**
     * testWrite
     *
     * @return void
     * @covers \AsyncSockets\Socket\UdpClientSocket::read
     */
    public function testRead()
    {
        $testValue   = fopen('php://temp', 'r+');
        $this->socket->expects(self::any())
                     ->method('getStreamResource')
                     ->willReturn($testValue);


        $frame = $this->object->read(new RawFramePicker());
        fclose($testValue);
        self::assertSame($this->data, (string) $frame, 'Incorrect frame');
    }


    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket = $this->createSocketInterface();
        $this->data   = md5(microtime(true));
        $this->remoteAddress = '255.255.255.255:1';
        $this->object = new UdpClientSocket($this->socket, $this->remoteAddress, $this->data);
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
    }
}
