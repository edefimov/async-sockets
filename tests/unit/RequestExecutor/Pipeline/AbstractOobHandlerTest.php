<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;

/**
 * Class AbstractOobHandlerTest
 */
class AbstractOobHandlerTest extends AbstractIoHandlerTest
{
    /**
     * @inheritDoc
     */
    protected function createIoHandlerInterface()
    {
        return $this->getMockForAbstractClass('AsyncSockets\RequestExecutor\Pipeline\AbstractOobHandler');
    }

    /**
     * Creates test operation
     *
     * @return OperationInterface
     */
    protected function createOperation()
    {
        return new ReadOperation();
    }

    /**
     * testReadingOobData
     *
     * @return void
     */
    public function testReadingOobData()
    {
        $data = sha1(microtime());

        $this->socket
            ->expects(self::once())
            ->method('read')
            ->willReturnCallback(function (FramePickerInterface $picker, $isOob) use ($data) {
                self::assertInstanceOf('AsyncSockets\Frame\RawFramePicker', $picker, 'Incorrect frame picker');
                self::assertTrue($isOob, 'OOB flag must be true here');

                return new Frame($data, sha1(mt_rand(10000, PHP_INT_MAX)));
            });

        $operation = $this->createOperation();
        $this->mockEventHandler
            ->expects(self::once())
            ->method('invokeEvent')
            ->willReturnCallback(
                function (Event $event) use ($data, $operation) {
                    self::assertInstanceOf('AsyncSockets\Event\ReadEvent', $event, 'Incorrect event');
                    /** @var ReadEvent $event */
                    self::assertSame(EventType::OOB, $event->getType(), 'Incorrect event type');
                    $this->validateEventContext($event);
                    self::assertSame($data, (string) $event->getFrame(), 'Incorrect data');
                    self::assertNull($event->getNextOperation(), 'Incorrect initial operation');
                }
            );

        $result = $this->handler->handle(
            $this->getMockedDescriptor($operation, $this->socket, RequestDescriptor::RDS_OOB),
            $this->executor,
            $this->mockEventHandler
        );

        self::assertNull($result, 'By default handler must return null.');
    }

    /**
     * testIfOperationIsChangedHandlerTerminates
     *
     * @return void
     * @depends testReadingOobData
     */
    public function testIfOperationIsChangedHandlerTerminates()
    {
        $data = sha1(microtime());

        $this->socket->expects(self::once())->method('read')->willReturn(
            new Frame($data, sha1(mt_rand(10000, PHP_INT_MAX)))
        );

        $operation = $this->createOperation();
        $this->mockEventHandler
            ->expects(self::once())
            ->method('invokeEvent')
            ->willReturnCallback(
                function (ReadEvent $event) use ($operation) {
                    $event->nextIs($operation);
                }
            );

        $result = $this->handler->handle(
            $this->getMockedDescriptor(
                $this->createOperation(),
                $this->socket,
                RequestDescriptor::RDS_OOB | RequestDescriptor::RDS_READ | RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler
        );

        self::assertSame($operation, $result, 'I/O handler must return new operation after changing.');
    }
}
