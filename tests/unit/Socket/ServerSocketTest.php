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

use AsyncSockets\Socket\ServerSocket;
use Tests\Application\Mock\PhpFunctionMocker;

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

        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->setCallable(
            function ($resource) {
                $data = \stream_get_meta_data($resource);
                $data['stream_type'] = 'tcp_socket';
                return $data;
            }
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_server')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_get_meta_data')->restoreNativeHandler();
    }
}
