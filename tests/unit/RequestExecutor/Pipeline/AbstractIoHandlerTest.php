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
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
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
    private $metadata;

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
        $this->metadata = $this->getMetadataStructure();
        $bag            = $this->getMockBuilder('AsyncSockets\RequestExecutor\SocketBagInterface')
                               ->setMethods([ 'getSocketMetaData' ])
                               ->getMockForAbstractClass();
        $this->executor = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                                ->setMethods(['socketBag'])
                                ->getMockForAbstractClass();

        $bag->expects(self::any())->method('getSocketMetaData')->willReturn($this->metadata);
        $this->executor->expects(self::any())->method('socketBag')->willReturn($bag);

        $this->mockEventHandler = $this->getMockBuilder('AsyncSockets\RequestExecutor\EventHandlerInterface')
                                       ->setMethods(['invokeEvent'])
                                       ->getMockForAbstractClass();

        $this->socket = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
             ->setMethods([ 'read', 'write', 'getStreamResource' ])
             ->getMockForAbstractClass();

        $this->handler = $this->createIoHandlerInterface();
    }
}
