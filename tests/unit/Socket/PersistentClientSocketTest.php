<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Socket\PersistentClientSocket;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class PersistentClientSocketTest
 */
class PersistentClientSocketTest extends ClientSocketTest
{
    /**
     * testCreatingSocketResourceWithoutKey
     *
     * @return void
     */
    public function testCreatingSocketResourceWithKey()
    {
        $key  = sha1(microtime(true));
        $host = 'tcp://localhost:' . mt_rand(1, 65535);
        $mock = $this->getMockBuilder('Countable')
            ->setMethods(['count'])
            ->getMockForAbstractClass();
        $mock->expects(self::once())->method('count')
            ->with(
                "{$host}/{$key}",
                null,
                null,
                null,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT,
                stream_context_get_default()
            )
            ->willReturn(fopen('php://temp', 'r+'));

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->setCallable([$mock, 'count']);

        $object = new PersistentClientSocket($key);
        $object->open($host);
    }

    /**
     * testCreatingSocketResourceWithoutKey
     *
     * @return void
     */
    public function testCreatingSocketResourceWithoutKey()
    {
        $host = 'tcp://localhost:' . mt_rand(1, 65535);
        $mock = $this->getMockBuilder('Countable')
            ->setMethods(['count'])
            ->getMockForAbstractClass();
        $mock->expects(self::once())->method('count')
            ->with(
                $host,
                null,
                null,
                null,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT,
                stream_context_get_default()
            )
            ->willReturn(fopen('php://temp', 'r+'));

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_client')->setCallable([$mock, 'count']);

        $object = new PersistentClientSocket();
        $object->open($host);
    }

    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        return new PersistentClientSocket();
    }
}
