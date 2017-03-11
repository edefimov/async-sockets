<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Exception;

use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class NetworkSocketExceptionTest
 */
class NetworkSocketExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Socket
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * testReturnSocket
     *
     * @return void
     */
    public function testReturnSocket()
    {
        $exception = $this->createException();
        self::assertSame($this->socket, $exception->getSocket(), 'Invalid socket');
    }

    /**
     * Create test exception
     *
     * @return NetworkSocketException
     */
    protected function createException()
    {
        return new NetworkSocketException($this->socket);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
                            ->getMockForAbstractClass();
    }
}
