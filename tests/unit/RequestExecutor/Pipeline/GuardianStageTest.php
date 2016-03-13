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

use AsyncSockets\Operation\NullOperation;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Pipeline\DisconnectStage;
use AsyncSockets\RequestExecutor\Pipeline\GuardianStage;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

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

        $descriptor  = $this->createOperationMetadata();
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
        $result = [];
        for ($i = 0; $i <= GuardianStage::MAX_ATTEMPTS_PER_SOCKET + 1; $i++) {
            $result = $this->stage->processStage([$descriptor]);
        }

        self::assertNotEmpty($result, 'GuardianHandler must return given objects.');
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
