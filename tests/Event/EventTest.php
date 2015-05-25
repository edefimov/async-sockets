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

use AsyncSockets\Event\Event;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class EventTest
 */
class EventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * RequestExecutorInterface
     *
     * @var RequestExecutorInterface
     */
    protected $executor;

    /**
     * SocketInterface
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * Context test object
     *
     * @var object
     */
    protected $context;

    /**
     * Create event object for test
     *
     * @param string $type Event type
     *
     * @return Event
     */
    protected function createEvent($type)
    {
        return new Event($this->executor, $this->socket, $this->context, $type);
    }

    /**
     * Get event type for testing
     *
     * @return string
     */
    protected function getEventType()
    {
        return md5(microtime());
    }

    /**
     * testGetters
     *
     * @return Event
     */
    public function testGetters()
    {
        $type  = $this->getEventType();
        $event = $this->createEvent($type);
        self::assertSame($this->executor, $event->getExecutor());
        self::assertSame($this->socket, $event->getSocket());
        self::assertSame($this->context, $event->getContext());
        self::assertEquals($type, $event->getType());
        self::assertFalse($event->isOperationCancelled(), 'Initial state of cancelled flag must be false');

        return $event;
    }

    /**
     * testSetters
     *
     * @return Event
     * @depends testGetters
     */
    public function testSetters()
    {
        $type  = $this->getEventType();
        $event = $this->createEvent($type);

        $event->cancelThisOperation(true);
        self::assertTrue($event->isOperationCancelled(), 'Failed to change cancel flag of request');
        return $event;
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->executor = $this->getMock('AsyncSockets\RequestExecutor\RequestExecutorInterface');
        $this->socket   = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $this->context  = $this->getMock('Countable');
    }
}
