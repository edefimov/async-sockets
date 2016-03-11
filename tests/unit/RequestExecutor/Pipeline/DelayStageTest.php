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

use AsyncSockets\Operation\DelayedOperation;
use AsyncSockets\RequestExecutor\Pipeline\DelayStage;

/**
 * Class DelayStageTest
 */
class DelayStageTest extends AbstractStageTest
{
    /**
     * testNotReturnWhileCallbackIsPending
     *
     * @return void
     */
    public function testNotReturnWhileCallbackIsPending()
    {
        $metadata = $this->createOperationMetadata();
        $mock     = $this->getMockBuilder('Countable')
                    ->setMethods(['count'])
                    ->getMockForAbstractClass();

        $operation = new DelayedOperation(
            $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface'),
            [$mock, 'count'],
            [$mock]
        );

        $mock->expects(self::once())
             ->method('count')
             ->with(
                 $metadata->getSocket(),
                 $this->executor,
                 $mock
             )
             ->willReturn(true);

        $metadata->expects(self::any())
                 ->method('getOperation')
                 ->willReturn($operation);

        $result = $this->stage->processStage([$metadata]);
        self::assertEmpty($result, 'When callable return true socket must not return');
    }

    /**
     * testPendingComplete
     *
     * @return void
     */
    public function testPendingComplete()
    {
        $metadata = $this->createOperationMetadata();
        $mock     = $this->getMockBuilder('Countable')
                         ->setMethods(['count'])
                         ->getMockForAbstractClass();

        $originalOperation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');
        $operation         = new DelayedOperation(
            $originalOperation,
            [$mock, 'count'],
            [$mock]
        );

        $mock->expects(self::once())
             ->method('count')
             ->with(
                 $metadata->getSocket(),
                 $this->executor,
                 $mock
             )
             ->willReturn(false);

        $metadata->expects(self::any())
                 ->method('getOperation')
                 ->willReturn($operation);

        $metadata->expects(self::any())
                 ->method('setOperation')
                 ->with($originalOperation);

        $result = $this->stage->processStage([$metadata]);
        self::assertNotEmpty($result, 'When callable return false socket must return');
    }

    /**
     * @inheritDoc
     */
    protected function createStage()
    {
        return new DelayStage($this->executor, $this->eventCaller);
    }
}
