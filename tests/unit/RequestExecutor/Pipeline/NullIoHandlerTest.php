<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Operation\NullOperation;
use AsyncSockets\RequestExecutor\Pipeline\NullIoHandler;

/**
 * Class NullIoHandlerTest
 */
class NullIoHandlerTest extends AbstractIoHandlerTest
{
    /**
     * @inheritDoc
     */
    protected function createIoHandlerInterface()
    {
        return new NullIoHandler();
    }

    /**
     * testDispatchEventAboutUnreadData
     *
     * @return void
     */
    public function testDispatchEventAboutUnreadData()
    {
        $nextOperation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');
        $this->mockEventHandler->expects(self::once())
            ->method('invokeEvent')
            ->willReturnCallback(function (IoEvent $event) use ($nextOperation) {
                self::assertSame(EventType::DATA_ALERT, $event->getType());
                self::assertSame($this->socket, $event->getSocket(), 'Invalid socket');
                $this->validateEventContext($event);
                $event->nextIs($nextOperation);
            });


        $result = $this->handler->handle(
            new NullOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
        );

        self::assertSame($nextOperation, $result, 'Incorrect return value of handle() method');
    }

    /**
     * testExceptionDuringEventWillBeHandled
     *
     * @return void
     */
    public function testExceptionDuringEventWillBeHandled()
    {
        $exception     = new SocketException();
        $this->mockEventHandler->expects(self::at(0))
                               ->method('invokeEvent')
                               ->willThrowException($exception);

        $this->mockEventHandler->expects(self::at(1))
                               ->method('invokeEvent')
                               ->willReturnCallback(function (SocketExceptionEvent $event) use ($exception) {
                                   self::assertSame($this->socket, $event->getSocket(), 'Invalid socket');
                                   self::assertSame($exception, $event->getException(), 'Invalid exception');
                                   $this->validateEventContext($event);
                               });


        $result = $this->handler->handle(
            new NullOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
        );

        self::assertNull($result, 'Incorrect return value of handle() method');
    }

    /**
     * testSupports
     *
     * @return void
     */
    public function testSupports()
    {
        self::assertTrue(
            $this->handler->supports(new NullOperation()),
            'Must support NullOperation'
        );
    }
}
