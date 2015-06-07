<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class AcceptEventTest
 */
class AcceptEventTest extends EventTest
{
    /**
     * SocketInterface
     *
     * @var SocketInterface
     */
    protected $clientSocket;

    /**
     * Random string for remote address argument
     *
     * @var string
     */
    protected $remoteAddress;

    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new AcceptEvent(
            $this->executor,
            $this->socket,
            $this->context,
            $this->clientSocket,
            $this->remoteAddress
        );
    }

    /** {@inheritdoc} */
    protected function getEventType()
    {
        return EventType::ACCEPT;
    }

    /** {@inheritdoc} */
    public function testGetters()
    {
        $event = parent::testGetters();
        /** @var AcceptEvent $event */
        self::assertSame($this->clientSocket, $event->getClientSocket(), 'Incorrect client socket');
        self::assertEquals($this->remoteAddress, $event->getRemoteAddress(), 'Incorrect remote address');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->clientSocket  = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $this->remoteAddress = md5(microtime()) . ':' . mt_rand(1, PHP_INT_MAX);
    }
}
