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
use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Pipeline\ReadIoHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class ReadIoHandlerTest
 */
class ReadIoHandlerTest extends AbstractOobHandlerTest
{
    /**
     * testReadOperationIsSupported
     *
     * @return void
     */
    public function testReadOperationIsSupported()
    {
        $mock = $this->getMockBuilder('AsyncSockets\Operation\ReadOperation')
                    ->enableProxyingToOriginalMethods()
                    ->getMock();

        self::assertTrue($this->handler->supports($mock), 'Incorrect supports result');
    }

    /**
     * testReadInSingleRequest
     *
     * @param FrameInterface $frame Return frame
     * @param string         $eventType Event to fire
     * @param bool           $mustBeReturned Flag whether next operation must be returned
     *
     * @return void
     * @dataProvider readDataProvider
     */
    public function testReadInSingleRequest(FrameInterface $frame, $eventType, $mustBeReturned)
    {
        $this->socket->expects(self::any())->method('read')->willReturnCallback(
            function (FramePickerInterface $picker) use ($frame) {
                $picker->pickUpData((string) $frame, '127.0.0.1');
                return $frame;
            }
        );
        $this->socket->expects(self::never())->method('write');

        $this->mockEventHandler->expects(self::once())
                               ->method('invokeEvent')
                               ->willReturnCallback(
                                   function (Event $event) use ($frame, $eventType) {
                                       $this->validateEventContext($event);
                                       self::assertEquals($eventType, $event->getType(), 'Incorrect event fired');
                                       if ($event instanceof ReadEvent) {
                                           self::assertSame($frame, $event->getFrame());
                                       }
                                   }
                               );

        $operation  = new ReadOperation();
        $descriptor = $this->getMockedDescriptor(
            $operation,
            $this->socket,
            RequestDescriptor::RDS_READ
        );

        $this->metadata[RequestExecutorInterface::META_BYTES_RECEIVED]             = mt_rand(1000, 10000);
        $this->metadata[RequestExecutorInterface::META_MIN_RECEIVE_SPEED]          = 1;
        $this->metadata[RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION] = 1e10;

        $descriptor->expects(self::exactly(2))
                   ->method('setMetadata')
                    ->willReturnCallback(function ($key, $value) use ($frame) {
                        switch ($key) {
                            case RequestExecutorInterface::META_BYTES_RECEIVED:
                                self::assertSame(
                                    $this->metadata[RequestExecutorInterface::META_BYTES_RECEIVED] +
                                        strlen((string) $frame),
                                    $value,
                                    'Unexpected bytes received value'
                                );
                                break;
                            case RequestExecutorInterface::META_RECEIVE_SPEED:
                                break;
                            default:
                                self::fail('Unexpected metadata change with key ' . $key);
                        }
                    });

        $result = $this->handler->handle(
            $operation,
            $descriptor,
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );

        if ($mustBeReturned) {
            self::assertNotNull($result, 'Incorrect return result');
        } else {
            self::assertNull($result, 'Incorrect return result');
        }
    }

    /**
     * testPartialFrameReading
     *
     * @return void
     */
    public function testPartialFrameReading()
    {
        $testFrame = new PartialFrame(new Frame(md5(microtime(true)), (string) mt_rand(0, PHP_INT_MAX)));

        $this->socket->expects(self::any())->method('read')->willReturn($testFrame);
        $this->socket->expects(self::never())->method('write');

        $this->mockEventHandler->expects(self::never())
                          ->method('invokeEvent');

        /** @var OperationInterface $operation */
        $operation = $this->getMockBuilder('AsyncSockets\Operation\ReadOperation')
                        ->enableProxyingToOriginalMethods()
                        ->getMock();

        $result = $this->handler->handle(
            $operation,
            $this->getMockedDescriptor(
                $operation,
                $this->socket,
                RequestDescriptor::RDS_READ
            ),
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );

        self::assertSame($operation, $result, 'PartialFrame must return exact same object after handle method');
    }

    /**
     * testExceptionOnReading
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionOnReading()
    {
        $testFrame = new Frame(md5(microtime(true)), (string) mt_rand(0, PHP_INT_MAX));

        $this->socket->expects(self::any())->method('read')->willReturn($testFrame);
        $this->socket->expects(self::never())->method('write');

        $exception = new NetworkSocketException($this->socket);
        $this->mockEventHandler->expects(self::once())
                          ->method('invokeEvent')
                          ->willThrowException($exception);

        $operation = new ReadOperation();
        $this->handler->handle(
            $operation,
            $this->getMockedDescriptor(
                $operation,
                $this->socket,
                RequestDescriptor::RDS_READ
            ),
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );

        self::fail('Exception must not be handled');
    }

    /**
     * testExceptionInSocketOnReading
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     */
    public function testExceptionInSocketOnReading()
    {
        $exception = new NetworkSocketException($this->socket);
        $this->socket->expects(self::any())->method('read')->willThrowException($exception);
        $this->socket->expects(self::never())->method('write');

        $this->mockEventHandler->expects(self::never())->method('invokeEvent');

        $operation = new ReadOperation();
        $this->handler->handle(
            $operation,
            $this->getMockedDescriptor(
                $operation,
                $this->socket,
                RequestDescriptor::RDS_READ
            ),
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );

        self::fail('Exception must not be handled');
    }

    /**
     * testThatAcceptExceptionWontFireAnyEvent
     *
     * @return void
     */
    public function testThatAcceptExceptionWontFireAnyEvent()
    {
        $this->socket->expects(self::any())->method('read')->willThrowException(new AcceptException($this->socket));
        $this->socket->expects(self::never())->method('write');

        $this->mockEventHandler->expects(self::never())->method('invokeEvent');

        $operation = new ReadOperation();
        $result    = $this->handler->handle(
            $operation,
            $this->getMockedDescriptor(
                $operation,
                $this->socket,
                RequestDescriptor::RDS_READ
            ),
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );

        self::assertInstanceOf(
            'AsyncSockets\Operation\ReadOperation',
            $result,
            'Incorrect operation for accept event'
        );
    }

    /**
     * testThatTooSlowRateCausesException
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\SlowSpeedTransferException
     */
    public function testThatTooSlowRateCausesException()
    {
        $this->socket->expects(self::any())->method('read')->willReturnCallback(
            function (FramePickerInterface $picker) {
                $picker->pickUpData('test', '127.0.0.1');
                return new PartialFrame($picker->createFrame());
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable(function () {
            return 2;
        });

        $operation  = new ReadOperation();
        $descriptor = $this->getMockedDescriptor(
            $operation,
            $this->socket,
            RequestDescriptor::RDS_READ
        );

        $this->metadata[RequestExecutorInterface::META_MIN_RECEIVE_SPEED]          = 1e10;
        $this->metadata[RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION] = 1;
        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME]     = 0;

        $this->handler->handle(
            $operation,
            $descriptor,
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );
    }

    /**
     * readDataProvider
     *
     * @return array
     */
    public function readDataProvider()
    {
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface'
        );

        /** @var SocketInterface $socket */
        return [
            [ new Frame(md5(microtime(true)), (string) mt_rand(0, PHP_INT_MAX)), EventType::READ, false ],
            [
                new AcceptedFrame(
                    md5(microtime(true)),
                    $socket
                ),
                EventType::ACCEPT,
                true
            ]
        ];
    }

    /**
     * Create test object
     *
     * @return IoHandlerInterface
     */
    protected function createIoHandlerInterface()
    {
        return new ReadIoHandler();
    }
}
