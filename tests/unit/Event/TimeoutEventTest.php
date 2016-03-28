<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\TimeoutEvent;

/**
 * Class TimeoutEventTest
 */
class TimeoutEventTest extends EventTest
{
    /** {@inheritdoc} */
    protected function getEventType()
    {
        return EventType::TIMEOUT;
    }

    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new TimeoutEvent($this->executor, $this->socket, $this->context, sha1(microtime()));
    }

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $when  = sha1(microtime(true));
        $event = new TimeoutEvent(
            $this->executor,
            $this->socket,
            $this->context,
            $when
        );

        self::assertSame($when, $event->when(), 'Incorrect when');
        self::assertFalse($event->isNextAttemptEnabled(), 'Next attempt must be disabled by default');

        $event->enableOneMoreAttempt();
        self::assertTrue($event->isNextAttemptEnabled(), 'Next attempt hasn\'t been changed');
    }
}
