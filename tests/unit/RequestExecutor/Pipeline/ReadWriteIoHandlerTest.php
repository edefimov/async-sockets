<?php
/**
 * Async Sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\ReadWriteOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Pipeline\DelegatingIoHandler;
use AsyncSockets\RequestExecutor\Pipeline\ReadWriteIoHandler;

/**
 * Class ReadWriteIoHandlerTest
 */
class ReadWriteIoHandlerTest extends AbstractIoHandlerTest
{
    /**
     * Read handler
     *
     * @var IoHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $readHandler;

    /**
     * Read handler
     *
     * @var IoHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $writeHandler;

    /**
     * Read operation
     *
     * @var ReadOperation
     */
    private $readOperation;

    /**
     * Write operation
     *
     * @var WriteOperation
     */
    private $writeOperation;

    /**
     * testReadWriteOperationIsSupported
     *
     * @return void
     */
    public function testReadWriteOperationIsSupported()
    {
        $mock = $this->getMockBuilder('AsyncSockets\Operation\ReadWriteOperation')
                     ->disableOriginalConstructor()
                     ->getMock();

        self::assertTrue($this->handler->supports($mock), 'Incorrect supports result');
    }

    /**
     * testReadPriorityExecution
     *
     * @param bool $readFirst     Flag if read operation must be called before write
     * @param int  $readPriority  Read call number
     * @param int  $writePriority Write call number
     *
     * @return void
     * @dataProvider priorityDataProvider
     */
    public function testReadPriorityExecution($readFirst, $readPriority, $writePriority)
    {
        $operation = new ReadWriteOperation(
            $readFirst,
            [$this->readOperation, $this->writeOperation]
        );

        $this->readHandler->expects(self::at($readPriority))
            ->method('handle')
            ->willReturn(null);

        $this->writeHandler->expects(self::at($writePriority))
            ->method('handle');

        $this->handler->handle(
            $operation,
            $this->getMockedDescriptor(
                $operation,
                $this->socket,
                RequestDescriptor::RDS_READ | RequestDescriptor::RDS_WRITE
            ),
            $this->executor,
            $this->mockEventHandler,
            $this->executionContext
        );

        self::assertNull($operation->getReadOperation(), 'Read operation is not removed');
        self::assertNull($operation->getWriteOperation(), 'Write operation is not removed');
    }

    /**
     * testSchedulingReadOperation
     *
     * @param OperationInterface $next Next operation
     *
     * @return void
     * @dataProvider operationDataProvider
     */
    public function testSchedulingReadOperation(OperationInterface $next)
    {
        $operation = new ReadWriteOperation(ReadWriteOperation::READ_FIRST, [clone $next]);

        $this->handlerFor($next)->expects(self::once())
            ->method('handle')
            ->willReturn($next);

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

        self::assertSame($operation, $result, 'Incorrect return value');
        self::assertSame(
            $next,
            $operation->getReadOperation() ?: $operation->getWriteOperation(),
            'Next operation is not prepared in working object'
        );
    }

    /**
     * handlerFor
     *
     * @param OperationInterface $operation
     *
     * @return IoHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function handlerFor(OperationInterface $operation)
    {
        if ($operation instanceof ReadOperation) {
            return $this->readHandler;
        }

        if ($operation instanceof WriteOperation) {
            return $this->writeHandler;
        }

        throw new \LogicException('No operation handler for class: ' . get_class($operation));
    }

    /**
     * priorityDataProvider
     *
     * @return array
     */
    public function priorityDataProvider()
    {
        return [
            [ReadWriteOperation::READ_FIRST, 0, 1],
            [ReadWriteOperation::WRITE_FIRST, 1, 0],
        ];
    }

    /**
     * operationDataProvider
     *
     * @return array
     */
    public function operationDataProvider()
    {
        return [
            [ new ReadOperation() ],
            [ new WriteOperation() ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function createIoHandlerInterface()
    {
        $result = new ReadWriteIoHandler();
        $result->setHandler(
            new DelegatingIoHandler(
                [
                    $this->readHandler,
                    $this->writeHandler,
                ]
            )
        );

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        $this->readOperation  = new ReadOperation();
        $this->writeOperation = new WriteOperation();

        $this->readHandler = $this->getMockBuilder('AsyncSockets\RequestExecutor\IoHandlerInterface')
            ->setMethods(['handle', 'supports'])
            ->getMockForAbstractClass();
        $this->readHandler->expects(self::any())
             ->method('supports')
             ->willReturnCallback(
                 function ($op) {
                     return $op instanceof ReadOperation;
                 }
             );

        $this->writeHandler = $this->getMockBuilder('AsyncSockets\RequestExecutor\IoHandlerInterface')
            ->setMethods(['handle', 'supports'])
            ->getMockForAbstractClass();
        $this->writeHandler->expects(self::any())
                          ->method('supports')
                          ->willReturnCallback(
                              function ($op) {
                                  return $op instanceof WriteOperation;
                              }
                          );

        parent::setUp();
    }
}
