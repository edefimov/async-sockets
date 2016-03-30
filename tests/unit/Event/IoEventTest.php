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

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Operation\OperationInterface;

/**
 * Class IoEventTest
 */
class IoEventTest extends EventTest
{
    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new IoEvent($this->executor, $this->socket, $this->context, $type);
    }

    /**
     * testSwitchers
     *
     * @return void
     */
    public function testSwitchers()
    {
        $event = $this->createEvent(EventType::READ);
        self::assertNull($event->getNextOperation(), 'Initial state of nex operation must be null');

        $event->nextIsRead();
        self::assertNotNull($event->getNextOperation(), 'Read operation was not changed');
        self::assertSame(
            OperationInterface::OPERATION_READ,
            $event->getNextOperation()->getType(),
            'Failed to switch to read operation'
        );

        $event->nextIsWrite();
        self::assertNotNull($event->getNextOperation(), 'Write operation was not changed');
        self::assertSame(
            OperationInterface::OPERATION_WRITE,
            $event->getNextOperation()->getType(),
            'Failed to switch to write operation'
        );

        $event->nextOperationNotRequired();
        self::assertNull($event->getNextOperation(), 'Failed to return back to initial state');
    }
}
