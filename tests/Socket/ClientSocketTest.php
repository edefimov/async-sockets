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

use AsyncSockets\Socket\ClientSocket;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class ClientSocketTest
 *
 * @SuppressWarnings("unused")
 * @SuppressWarnings("TooManyMethods")
 */
class ClientSocketTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var ClientSocket
     */
    private $socket;

    /**
     * testExceptionWillBeThrowsOnCreateFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionCode 500
     * @expectedExceptionMessage Tested socket creation
     */
    public function testExceptionWillBeThrowsOnCreateFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client');
        $mocker->setCallable(function ($remoteSocket, &$errno, &$errstr) {
            self::assertEquals('php://temp', $remoteSocket, 'Incorrect address passed to stream_socket_client');
            $errno  = 500;
            $errstr = 'Tested socket creation';
            return false;
        });

        $this->socket->open('php://temp');
    }

    /**
     * testExceptionWillBeThrowsOnSetBlockingFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionWillBeThrowsOnSetBlockingFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking');
        $mocker->setCallable(function ($resource, $isBlocking) {
            self::assertEquals(false, $isBlocking, 'Unexpected value passed');
            return false;
        });

        $this->socket->open('it has no meaning here');
        $this->socket->setBlocking(false);
    }

    /**
     * testReadingPartialContent
     *
     * @return void
     */
    public function testReadingPartialContent()
    {
        $testString = "HTTP 200 OK\nServer: test-reader\n\n";
        $counter    = 0;

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('fread');
        $mocker->setCallable(function () use ($testString, &$counter) {
            return $counter < strlen($testString) ? $testString[$counter++] : '';
        });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () use ($testString, $counter) {
                return $counter < strlen($testString) ? $testString[$counter] : false;
            }
        );

        $this->socket->open('it has no meaning here');
        $retString = $this->socket->read();
        self::assertEquals($testString, $retString, 'Unexpected result was read');
    }

    /**
     * testWritePartialContent
     *
     * @return void
     */
    public function testWritePartialContent()
    {
        $testString = "GET / HTTP/1.1\nHost: github.com\n\n";
        $counter    = 0;
        $retString  = '';

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('fwrite');
        $mocker->setCallable(function ($handle, $data) use ($testString, &$counter, &$retString) {
            if ($data && $counter < strlen($testString)) {
                ++$counter;
                $retString .= $data[0];
                return 1;
            }

            return 0;
        });

        $this->socket->open('it has no meaning here');
        $this->socket->write($testString);
        self::assertEquals($testString, $retString, 'Unexpected result was read');
    }

    /**
     * testExceptionWillBeThrownOnReadError
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionWillBeThrownOnReadError()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('fread');
        $mocker->setCallable(function () {
            return false;
        });

        $this->socket->open('it has no meaning here');
        $this->socket->read();
    }

    /**
     * testExceptionWillBeThrownOnWriteError
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionWillBeThrownOnWriteError()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('fwrite');
        $mocker->setCallable(function () {
            return false;
        });

        $this->socket->open('it has no meaning here');
        $this->socket->write('test');
    }

    /**
     * testCloseWillBeInvokedOnDestruct
     *
     * @return void
     */
    public function testCloseWillBeInvokedOnDestruct()
    {
        $object = $this->getMockBuilder('AsyncSockets\Socket\ClientSocket')
            ->setMethods(['close'])
            ->getMock();
        $object->expects(self::once())
            ->method('close')
            ->with();
        /** @var ClientSocket $object */
        $object->__destruct();
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket = new ClientSocket();

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client');
        $mocker->setCallable(function () {
            return fopen('php://temp', 'rw');
        });
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fread')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
    }
}
