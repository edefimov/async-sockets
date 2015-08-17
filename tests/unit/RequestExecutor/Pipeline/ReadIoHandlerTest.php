<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
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
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\Pipeline\ReadIoHandler;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class ReadIoHandlerTest
 */
class ReadIoHandlerTest extends AbstractIoHandlerTest
{
    /**
     * testReadOperationIsSupported
     *
     * @return void
     */
    public function testReadOperationIsSupported()
    {
        $mock = $this->getMockBuilder('AsyncSockets\RequestExecutor\ReadOperation')
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
        $this->socket->expects(self::any())->method('read')->willReturn($frame);
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

        $result = $this->handler->handle(
            new ReadOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
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
        $testFrame = new PartialFrame(new Frame(md5(microtime(true))));

        $this->socket->expects(self::any())->method('read')->willReturn($testFrame);
        $this->socket->expects(self::never())->method('write');

        $this->mockEventHandler->expects(self::never())
                          ->method('invokeEvent');

        /** @var OperationInterface $operation */
        $operation = $this->getMockBuilder('AsyncSockets\RequestExecutor\ReadOperation')
                        ->enableProxyingToOriginalMethods()
                        ->getMock();

        $result = $this->handler->handle(
            $operation,
            $this->socket,
            $this->executor,
            $this->mockEventHandler
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
        $testFrame = new Frame(md5(microtime(true)));

        $this->socket->expects(self::any())->method('read')->willReturn($testFrame);
        $this->socket->expects(self::never())->method('write');

        $exception = new NetworkSocketException($this->socket);
        $this->mockEventHandler->expects(self::once())
                          ->method('invokeEvent')
                          ->willThrowException($exception);

        $this->handler->handle(
            new ReadOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
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

        $this->handler->handle(
            new ReadOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
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

        $result = $this->handler->handle(
            new ReadOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
        );

        self::assertInstanceOf(
            'AsyncSockets\RequestExecutor\ReadOperation',
            $result,
            'Incorrect operation for accept event'
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
            [ new Frame(md5(microtime(true))), EventType::READ, false ],
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
