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

use AsyncSockets\Event\DataAlertEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Operation\NullOperation;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Pipeline\DisconnectStage;
use AsyncSockets\RequestExecutor\Pipeline\GuardianStage;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class GuardianStageTest
 */
class GuardianStageTest extends AbstractStageTest
{
    /**
     * Disconnect stage mock
     *
     * @var DisconnectStage|\PHPUnit_Framework_MockObject_MockObject
     */
    private $disconnectStage;

    /**
     * testThatUnknownOrCompleteOperationWillBeSkipped
     *
     * @param bool $isComplete Flag whether operation is compele
     *
     * @return void
     * @dataProvider boolDataProvider
     */
    public function testThatUnknownOrCompleteOperationWillBeSkipped($isComplete)
    {
        $this->disconnectStage->expects(self::never())->method('disconnect');

        $descriptor = $this->createOperationMetadata();
        $descriptor->expects(self::any())
            ->method('getOperation')
            ->willReturn($this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface'));
        $descriptor->expects(self::any())
            ->method('getMetadata')
            ->willReturn(
                [
                    RequestExecutorInterface::META_REQUEST_COMPLETE => $isComplete
                ]
            );

        $result = $this->stage->processStage([$descriptor]);
        self::assertNotEmpty($result, 'GuardianHandler must return given objects.');
    }

    /**
     * testKillZombie
     *
     * @return void
     */
    public function testKillZombie()
    {
        $descriptor  = $this->createOperationMetadata();
        $descriptor->expects(self::any())
                 ->method('getOperation')
                 ->willReturn(new NullOperation());
        $descriptor->expects(self::any())
                 ->method('getSocket')
                 ->willReturn($this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface'));
        $descriptor->expects(self::any())
                 ->method('getMetadata')
                 ->willReturn(
                     [
                         RequestExecutorInterface::META_REQUEST_COMPLETE => false
                     ]
                 );

        $this->disconnectStage->expects(self::once())
            ->method('disconnect')
            ->with($descriptor);

        $this->eventCaller->expects(self::once())
            ->method('callExceptionSubscribers')
            ->willReturnCallback(function (OperationMetadata $descriptor, \Exception $e) {
                self::assertInstanceOf('AsyncSockets\Exception\UnmanagedSocketException', $e, 'Incorrect exception');
            });
        $result        = [ $descriptor ];
        $safetyCounter = 200;
        while ($result && $safetyCounter) {
            $result = $this->stage->processStage([$descriptor]);
            --$safetyCounter;
        }

        self::assertEmpty($result, 'GuardianHandler must return given objects.');
    }

    /**
     * testDispatchEventAboutUnreadData
     *
     * @return void
     */
    public function testDispatchEventAboutUnreadData()
    {
        return;
        $nextOperation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');
        $this->eventCaller->expects(self::once())
                          ->method('invokeEvent')
                          ->willReturnCallback(
                              function (DataAlertEvent $event) use ($nextOperation) {
                                  self::assertSame(EventType::DATA_ALERT, $event->getType());
                                  self::assertSame($this->socket, $event->getSocket(), 'Invalid socket');
                                  $this->validateEventContext($event);
                                  $event->nextIs($nextOperation);
                              }
                          );


        $result = $this->stage->handle(
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
        return;
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
     * boolDataProvider
     *
     * @return array
     */
    public function boolDataProvider()
    {
        return [ [false], [true] ];
    }
    /**
     * @inheritDoc
     */
    protected function createStage()
    {
        return new GuardianStage($this->executor, $this->eventCaller, $this->disconnectStage);
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        $this->disconnectStage = $this->getMockBuilder('AsyncSockets\RequestExecutor\Pipeline\DisconnectStage')
                                      ->setMethods(['disconnect'])
                                      ->disableOriginalConstructor()
                                      ->getMockForAbstractClass();
        parent::setUp();
    }
}
