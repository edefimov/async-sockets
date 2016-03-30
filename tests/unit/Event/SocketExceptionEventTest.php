<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Exception\SocketException;

/**
 * Class SocketExceptionEventTest
 */
class SocketExceptionEventTest extends EventTest
{
    /**
     * SocketException
     *
     * @var SocketException
     */
    protected $exception;

    /**
     * Event
     *
     * @var Event
     */
    protected $originalEvent;

    /** {@inheritdoc} */
    protected function getEventType()
    {
        return EventType::EXCEPTION;
    }

    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new SocketExceptionEvent(
            $this->exception,
            $this->executor,
            $this->socket,
            $this->context,
            $this->originalEvent
        );
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->exception     = new SocketException('Test', 123);
        $this->originalEvent = new Event($this->executor, $this->socket, $this->context, EventType::CONNECTED);
    }

    /** {@inheritdoc} */
    public function testGetters()
    {
        $event = parent::testGetters();
        /** @var SocketExceptionEvent $event */
        self::assertSame($this->exception, $event->getException(), 'Incorrect exception object');

        return $event;
    }
}
