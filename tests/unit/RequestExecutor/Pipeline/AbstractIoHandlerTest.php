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
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class AbstractIoHandlerTest
 */
abstract class AbstractIoHandlerTest extends AbstractTestCase
{
    use MetadataStructureAwareTrait;

    /**
     * Event handler
     *
     * @var EventHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockEventHandler;

    /**
     * Executor
     *
     * @var RequestExecutorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $executor;

    /**
     * Socket
     *
     * @var SocketInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $socket;

    /**
     * Test object
     *
     * @var IoHandlerInterface
     */
    protected $handler;

    /**
     * Metadata test array
     *
     * @var array
     */
    protected $metadata;

    /**
     * Execution context
     *
     * @var ExecutionContext
     */
    protected $executionContext;

    /**
     * Create test object
     *
     * @return IoHandlerInterface
     */
    abstract protected function createIoHandlerInterface();

    /**
     * Validate event user context
     *
     * @param Event $event Event object
     *
     * @return void
     */
    protected function validateEventContext(Event $event)
    {
        self::assertSame(
            $this->metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
            $event->getContext(),
            'Incorrect context'
        );
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->executionContext = new ExecutionContext();

        $this->metadata = $this->getMetadataStructure();
        $bag            = $this->getMockBuilder('AsyncSockets\RequestExecutor\SocketBagInterface')
                               ->setMethods([ 'getSocketMetaData' ])
                               ->getMockForAbstractClass();
        $this->executor = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                                ->setMethods(['socketBag'])
                                ->getMockForAbstractClass();

        $bag->expects(self::any())
            ->method('getSocketMetaData')
            ->willReturnCallback(function () {
                return $this->metadata;
            });

        $this->executor->expects(self::any())->method('socketBag')->willReturn($bag);

        $this->mockEventHandler = $this->getMockBuilder('AsyncSockets\RequestExecutor\EventHandlerInterface')
                                       ->setMethods(['invokeEvent'])
                                       ->getMockForAbstractClass();

        $this->socket = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
             ->setMethods([ 'read', 'write', 'getStreamResource' ])
             ->getMockForAbstractClass();

        $this->handler = $this->createIoHandlerInterface();
    }

    /**
     * Return mocked descriptor
     *
     * @param OperationInterface $operation Operation interface
     * @param SocketInterface    $socket Socket object
     * @param int                $state State for descriptor
     *
     * @return RequestDescriptor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockedDescriptor(OperationInterface $operation, SocketInterface $socket, $state)
    {
        $mock = $this->getMockBuilder('AsyncSockets\RequestExecutor\Metadata\RequestDescriptor')
                    ->setMethods([ 'getOperation', 'getSocket', 'getMetadata', 'setMetadata' ])
                    ->disableOriginalConstructor()
                    ->getMockForAbstractClass();

        /** @var RequestDescriptor|\PHPUnit_Framework_MockObject_MockObject $mock */
        $mock->expects(self::any())->method('getOperation')->willReturn($operation);
        $mock->expects(self::any())->method('getSocket')->willReturn($socket);
        $mock->expects(self::any())->method('getMetadata')->willReturnCallback(function () {
            return $this->metadata;
        });

        $mock->setState($state);

        return $mock;
    }
}
