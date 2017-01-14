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
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Operation\InProgressWriteOperation;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Pipeline\WriteIoHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class WriteIoHandlerTest
 */
class WriteIoHandlerTest extends AbstractOobHandlerTest
{
    /**
     * @inheritDoc
     */
    protected function createOperation()
    {
        return new WriteOperation();
    }

    /**
     * testWriteOperationIsSupported
     *
     * @param string $class Operation class name
     *
     * @return void
     * @dataProvider writeOperationsDataProvider
     */
    public function testWriteOperationIsSupported($class)
    {
        /** @var OperationInterface $mock */
        $mock = $this->getMockBuilder($class)
                     ->enableProxyingToOriginalMethods()
                     ->disableOriginalConstructor()
                     ->getMockForAbstractClass();

        self::assertTrue($this->handler->supports($mock), 'Incorrect supports result');
    }

    /**
     * testWriteInSingleRequest
     *
     * @return void
     */
    public function testWriteInSingleRequest()
    {
        $testData   = md5(microtime(true));
        $lengthData = strlen($testData);

        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::any())->method('write')->willReturn($lengthData);

        $this->mockEventHandler->expects(self::once())
                          ->method('invokeEvent')
                          ->willReturnCallback(function (Event $event) {
                              $this->validateEventContext($event);
                              self::assertEquals(EventType::WRITE, $event->getType(), 'Incorrect event fired');
                              self::assertInstanceOf(
                                  'AsyncSockets\Event\WriteEvent',
                                  $event,
                                  'Unexpected event class'
                              );
                          });

        $descriptor = $this->getMockedDescriptor(
            new WriteOperation($testData),
            $this->socket,
            RequestDescriptor::RDS_WRITE
        );

        $this->metadata[RequestExecutorInterface::META_BYTES_SENT] = mt_rand(1000, 10000);
        $descriptor->expects(self::once())
            ->method('setMetadata')
            ->with(
                RequestExecutorInterface::META_BYTES_SENT,
                $this->metadata[RequestExecutorInterface::META_BYTES_SENT] + $lengthData
            );

        $result = $this->handler->handle(
            $descriptor,
            $this->executor,
            $this->mockEventHandler
        );
        self::assertNull($result, 'Incorrect return result');
    }

    /**
     * testExceptionOnWriting
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionOnWriting()
    {
        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::any())->method('write');

        $exception = new NetworkSocketException($this->socket);
        $this->mockEventHandler->expects(self::once())
                               ->method('invokeEvent')
                               ->willThrowException($exception);

        $this->handler->handle(
            $this->getMockedDescriptor(
                new WriteOperation('some data'),
                $this->socket,
                RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler
        );

        self::fail('Exception must not be handled');
    }

    /**
     * testExceptionInSocketOnWriting
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionInSocketOnWriting()
    {
        $exception = new NetworkSocketException($this->socket);
        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::any())->method('write')->willThrowException($exception);

        $this->handler->handle(
            $this->getMockedDescriptor(
                new WriteOperation('some data'),
                $this->socket,
                RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler
        );

        self::fail('Exception must not be handled');
    }

    /**
     * testThatEmptyDataWontBeSent
     *
     * @return void
     */
    public function testThatEmptyDataWontBeSent()
    {
        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::never())->method('write');

        $result = $this->handler->handle(
            $this->getMockedDescriptor(
                new WriteOperation(),
                $this->socket,
                RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler
        );
        self::assertNull($result, 'Incorrect return result');
    }

    /**
     * testThatEventDataWillOverwriteInitial
     *
     * @return void
     */
    public function testThatEventDataWillOverwriteInitial()
    {
        $testData = md5(microtime(true));

        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::once())->method('write')->with($testData)->willReturn(strlen($testData));

        $this->mockEventHandler->expects(self::once())
                          ->method('invokeEvent')
                          ->willReturnCallback(function (WriteEvent $event) use ($testData) {
                              $event->getOperation()->setData($testData);
                          });

        $result = $this->handler->handle(
            $this->getMockedDescriptor(
                new WriteOperation(''),
                $this->socket,
                RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler
        );
        self::assertNull($result, 'Incorrect return result');
    }

    /**
     * testThatInProgressWriteOperationWillNotFireEvent
     *
     * @return void
     */
    public function testThatInProgressWriteOperationWillNotFireEvent()
    {
        $testData  = md5(microtime(true));
        $operation = new InProgressWriteOperation();

        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::any())->method('write')->willReturnOnConsecutiveCalls(strlen($testData));

        $this->mockEventHandler->expects(self::never())->method('invokeEvent');

        $result = $this->handler->handle(
            $this->getMockedDescriptor(
                $operation,
                $this->socket,
                RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler
        );
        self::assertNull($result, 'Incorrect return result');
    }

    /**
     * testThatPartialDataWillWrittenLater
     *
     * @return void
     */
    public function testThatPartialDataWillWrittenLater()
    {
        $testData = md5(microtime(true));

        $this->socket->expects(self::never())->method('read');
        $this->socket->expects(self::any())->method('write')->willReturnOnConsecutiveCalls(4, 4, 4, 4, 0);

        $this->mockEventHandler->expects(self::once())
                          ->method('invokeEvent')
                          ->willReturnCallback(function (Event $event) {
                              $this->validateEventContext($event);
                              self::assertEquals(EventType::WRITE, $event->getType(), 'Incorrect event fired');
                              self::assertInstanceOf(
                                  'AsyncSockets\Event\WriteEvent',
                                  $event,
                                  'Unexpected event class'
                              );
                          });

        $result = $this->handler->handle(
            $this->getMockedDescriptor(new WriteOperation($testData), $this->socket, RequestDescriptor::RDS_WRITE),
            $this->executor,
            $this->mockEventHandler
        );

        self::assertInstanceOf(
            'AsyncSockets\Operation\InProgressWriteOperation',
            $result,
            'Incorrect return result'
        );
    }

    /**
     * writeOperationsDataProvider
     *
     * @return array
     */
    public function writeOperationsDataProvider()
    {
        return [
            ['AsyncSockets\Operation\WriteOperation'],
            ['AsyncSockets\Operation\InProgressWriteOperation']
        ];
    }

    /** {@inheritdoc} */
    protected function createIoHandlerInterface()
    {
        return new WriteIoHandler();
    }
}
