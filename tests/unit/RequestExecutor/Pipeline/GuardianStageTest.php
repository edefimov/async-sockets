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
use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Frame\EmptyFramePicker;
use AsyncSockets\Operation\NullOperation;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
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
     * @param OperationInterface $operation Operation to test
     *
     * @return void
     * @dataProvider operationDataProvider
     */
    public function testKillZombie(OperationInterface $operation)
    {
        $descriptor  = $this->createOperationMetadata();
        $descriptor->expects(self::any())
                 ->method('getOperation')
                 ->willReturn($operation);
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

        for ($i = 0; $i < GuardianStage::MAX_ATTEMPTS_PER_SOCKET; $i++) {
            $descriptor->expects(self::at($i))
                ->method('invokeEvent')
                ->willReturnCallback(function (Event $event) use ($i) {
                    self::assertInstanceOf('AsyncSockets\Event\DataAlertEvent', $event, 'Incorrect event class');
                    self::assertSame(EventType::DATA_ALERT, $event->getType(), 'Incorrect event type');
                    /** @var DataAlertEvent $event */
                    self::assertSame($i, $event->getAttempt(), 'Incorrect attempt');
                    self::assertSame(
                        GuardianStage::MAX_ATTEMPTS_PER_SOCKET,
                        $event->getTotalAttempts(),
                        'Incorrect total attempts'
                    );
                });
        }

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
     * testCanChangeNextOperationAfterEvent
     *
     * @param OperationInterface $operation Operation to test
     *
     * @return void
     * @dataProvider operationDataProvider
     */
    public function testCanChangeNextOperationAfterEvent(OperationInterface $operation)
    {
        $descriptor  = $this->createOperationMetadata();
        $descriptor->expects(self::any())
                   ->method('getOperation')
                   ->willReturn($operation);
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


        $nextOperation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');
        $descriptor->expects(self::once())
                   ->method('setOperation')
                   ->with($nextOperation);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->with($descriptor)
                          ->willReturnCallback(
                              function ($descriptor, DataAlertEvent $event) use ($nextOperation) {
                                  $event->nextIs($nextOperation);
                              }
                          );

        $this->stage->processStage([$descriptor]);
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
     * operationDataProvider
     *
     * @return array
     */
    public function operationDataProvider()
    {
        return [
            [new NullOperation()],
            [new ReadOperation(new EmptyFramePicker())]
        ];
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
