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
 */
class ClientSocketTest extends AbstractSocketTest
{
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

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();

        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client');
        $mocker->setCallable(function () {
            return fopen('php://temp', 'rw');
        });
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->restoreNativeHandler();
    }

    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        return new ClientSocket();
    }


}
