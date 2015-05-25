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

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\WriteEvent;

/**
 * Class ReadEventTest
 */
class WriteEventTest extends IoEventTest
{
    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new WriteEvent($this->executor, $this->socket, $this->context);
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
        self::assertFalse($event->hasData(), 'Incorrect data initial state');
        self::assertNull($event->getData(), 'Incorrect data initial state');
    }

    /**
     * testSetData
     *
     * @return void
     */
    public function testSetData()
    {
        $data  = md5(microtime());
        $event = $this->createEvent(null);
        $event->setData($data);
        self::assertEquals($data, $event->getData(), 'Data are not set');
        self::assertTrue($event->hasData(), 'Event must have data here');
    }

    /**
     * testClearData
     *
     * @return void
     * @depends testSetData
     */
    public function testClearData()
    {
        $data  = md5(microtime());
        $event = $this->createEvent(null);
        $event->setData($data);
        $event->clearData();
        self::assertFalse($event->hasData(), 'Write buffer was not cleared');
        self::assertNull($event->getData(), 'Strange data returned');
    }
}
