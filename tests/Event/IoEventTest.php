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
use AsyncSockets\Event\IoEvent;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

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
        self::assertEquals(
            RequestExecutorInterface::OPERATION_READ,
            $event->getNextOperation(),
            'Failed to switch to read operation'
        );

        $event->nextIsWrite();
        self::assertEquals(
            RequestExecutorInterface::OPERATION_WRITE,
            $event->getNextOperation(),
            'Failed to switch to write operation'
        );

        $event->nextOperationNotRequired();
        self::assertNull($event->getNextOperation(), 'Failed to return back to initial state');
    }
}
