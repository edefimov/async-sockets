<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Operation\WriteOperation;

/**
 * Class ReadEventTest
 */
class WriteEventTest extends IoEventTest
{
    /**
     * Mock object for event
     *
     * @var WriteOperation
     */
    private $operation;

    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new WriteEvent($this->operation, $this->executor, $this->socket, $this->context);
    }

    /** {@inheritdoc} */
    protected function getEventType()
    {
        return EventType::WRITE;
    }

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $event = $this->createEvent(null);
        self::assertSame($this->operation, $event->getOperation(), 'Incorrect data initial state');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->operation = $this->getMockBuilder('AsyncSockets\Operation\WriteOperation')
                                ->getMockForAbstractClass();
    }
}
