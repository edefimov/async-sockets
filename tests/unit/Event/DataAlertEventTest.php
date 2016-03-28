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

use AsyncSockets\Event\DataAlertEvent;
use AsyncSockets\Event\EventType;

/**
 * Class DataAlertEventTest
 */
class DataAlertEventTest extends EventTest
{
    /** {@inheritdoc} */
    protected function getEventType()
    {
        return EventType::DATA_ALERT;
    }

    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new DataAlertEvent($this->executor, $this->socket, $this->context, 1, 10);
    }

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $attempt = mt_rand(0, PHP_INT_MAX);
        $total   = mt_rand(0, PHP_INT_MAX);

        $event = new DataAlertEvent(
            $this->executor,
            $this->socket,
            $this->context,
            $attempt,
            $total
        );

        self::assertSame($attempt, $event->getAttempt(), 'Incorrect attempt');
        self::assertSame($total, $event->getTotalAttempts(), 'Incorrect total attempts');
    }
}
