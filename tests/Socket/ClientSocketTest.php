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

use AsyncSockets\Socket\ChunkSocketResponse;
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
            function () use ($testString, &$counter) {
                return $counter < strlen($testString) ? $testString[$counter] : '';
            }
        );

        $this->socket->open('it has no meaning here');
        $retString = $this->socket->read()->getData();
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

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto');
        $mocker->setCallable(function ($handle, $data) {
            return strlen($data);
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
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(function () {
            return false;
        });

        $this->socket->open('it has no meaning here');
        $this->socket->read();
    }

    /**
     * testChunkReading
     *
     * @return void
     */
    public function testChunkReading()
    {
        $data      = 'I will pass this test';
        $splitData = str_split($data, 1);
        $freadMock = $this->getMock('Countable', ['count']);
        $freadMock->expects(self::any())
            ->method('count')
            ->will(new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($splitData));
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(function () {
            return 1;
        });
        PhpFunctionMocker::getPhpFunctionMocker('fread')->setCallable(function () use ($freadMock) {
            /** @var \Countable $freadMock */
            return $freadMock->count();
        });

        $streamSocketRecvFromData = [];
        foreach ($splitData as $letter) {
            $streamSocketRecvFromData[] = $letter;
            $streamSocketRecvFromData[] = false;
            if (mt_rand(1, 10) % 2) {
                $streamSocketRecvFromData[] = false;
            }
        }
        $streamSocketRecvFromData[] = '';
        $socketReadMock = $this->getMock('Countable', ['count']);
        $socketReadMock->expects(self::any())
            ->method('count')
            ->will(new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($streamSocketRecvFromData));
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () use ($socketReadMock) {
                /** @var \Countable $socketReadMock */
                return $socketReadMock->count();
            }
        );

        $response = null;
        $this->socket->open('it has no meaning here');
        do {
            $response = $this->socket->read($response);
        } while ($response instanceof ChunkSocketResponse);

        self::assertInstanceOf('AsyncSockets\Socket\SocketResponse', $response);
        self::assertNotInstanceOf(
            'AsyncSockets\Socket\ChunkSocketResponse',
            $response,
            'Final response must not be chunk'
        );

        self::assertEquals($data, (string) $response, 'Received data is incorrect');
    }

    /**
     * testNothingHappenIfNotSelected
     *
     * @return void
     */
    public function testNothingHappenIfNotSelected()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(function () {
            return 0;
        });

        $this->socket->open('it has no meaning here');
        self::assertInstanceOf('AsyncSockets\Socket\SocketResponse', $this->socket->read(), 'Strange response');
    }

    /**
     * testActualReadingFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testActualReadingFail()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(function () {
            return 1;
        });

        PhpFunctionMocker::getPhpFunctionMocker('fread')->setCallable(function () {
            /** @var \Countable $freadMock */
            return false;
        });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return 'x';
            }
        );

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

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(function() {
            return 'php://temp';
        });
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_set_blocking')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fread')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('fwrite')->restoreNativeHandler();
    }
}
