<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\RemoveFinishedSocketsEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class RemoveFinishedSocketsEventHandlerTest
 */
class RemoveFinishedSocketsEventHandlerTest extends EventHandlerInterfaceTest
{
    /**
     * Test object
     *
     * @var RemoveFinishedSocketsEventHandler
     */
    private $object;

    /**
     * Mock handler
     *
     * @var EventHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockedHandler;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->mockedHandler = $this->createMockedHandler();
        $this->object        = new RemoveFinishedSocketsEventHandler($this->mockedHandler);
    }

    /**
     * testNoHandlerCanBePassedInConstructor
     *
     * @return void
     */
    public function testNoHandlerCanBePassedInConstructor()
    {
        $executor = $this->createRequestExecutorMock();
        $socket   = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');

        /** @var SocketInterface $socket */

        $object = new RemoveFinishedSocketsEventHandler(null);
        foreach ($this->getEventTypes() as $eventType) {
            $object->invokeEvent(
                new Event($executor, $socket, null, $eventType)
            );
        }
    }

    /**
     * testThatOriginalHandlerWillBeCalled
     *
     * @return void
     */
    public function testThatOriginalHandlerWillBeCalled()
    {
        $eventTypes = $this->getEventTypes();

        $this->mockedHandler->expects(self::exactly(count($eventTypes)))->method('invokeEvent');
        $executor = $this->createRequestExecutorMock();
        $socket   = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');

        /** @var SocketInterface $socket */
        foreach ($eventTypes as $eventType) {
            $event = new Event($executor, $socket, null, $eventType);
            $this->object->invokeEvent($event);
        }
    }

    /**
     * testThatSocketWillBeRemovedOnFinalizeEvent
     *
     * @return void
     */
    public function testThatSocketWillBeRemovedOnFinalizeEvent()
    {
        $executor = $this->createRequestExecutorMock();
        $socket   = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
        $bag      = $executor->socketBag();
        /** @var SocketBag|\PHPUnit_Framework_MockObject_MockObject $bag */
        $bag->expects(self::once())->method('hasSocket')->with($socket)->willReturn(true);
        $bag->expects(self::once())->method('removeSocket')->with($socket);

        /** @var SocketInterface $socket */
        $this->object->invokeEvent(
            new Event($executor, $socket, null, EventType::FINALIZE)
        );
    }

    /**
     * testThatOtherEventsWillNotTryToRemoveSocket
     *
     * @return void
     */
    public function testThatOtherEventsWillNotTryToRemoveSocket()
    {
        $executor = $this->createRequestExecutorMock();
        $socket   = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
        $bag      = $executor->socketBag();
        /** @var SocketBag|\PHPUnit_Framework_MockObject_MockObject $bag */
        $bag->expects(self::never())->method('hasSocket')->with($socket);
        $bag->expects(self::never())->method('removeSocket')->with($socket);

        /** @var SocketInterface $socket */
        foreach ($this->getEventTypes() as $eventType) {
            if ($eventType === EventType::FINALIZE) {
                continue;
            }

            $event = new Event($executor, $socket, null, $eventType);
            $this->object->invokeEvent($event);
        }
    }

    /**
     * testThatIfSocketRemovedByExternalHandlerRemoveSocketMethodWillNotCalled
     *
     * @return void
     */
    public function testThatIfSocketRemovedByExternalHandlerRemoveSocketMethodWillNotCalled()
    {
        $executor = $this->createRequestExecutorMock();
        $socket   = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
        $bag      = $executor->socketBag();
        /** @var SocketBag|\PHPUnit_Framework_MockObject_MockObject $bag */
        $bag->expects(self::once())->method('hasSocket')->with($socket)->willReturn(false);
        $bag->expects(self::never())->method('removeSocket')->with($socket);

        /** @var SocketInterface $socket */
        $this->object->invokeEvent(
            new Event($executor, $socket, null, EventType::FINALIZE)
        );
    }

    /**
     * createRequestExecutorMock
     *
     * @return RequestExecutorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createRequestExecutorMock()
    {
        $executor = $this->getMockForAbstractClass(
            'AsyncSockets\RequestExecutor\RequestExecutorInterface',
            [],
            '',
            true,
            true,
            true,
            ['socketBag']
        );

        $socketBag = $this->getMock(
            'AsyncSockets\RequestExecutor\Metadata\SocketBag',
            ['hasSocket', 'removeSocket'],
            [$executor, mt_rand(1, PHP_INT_MAX), mt_rand(1, PHP_INT_MAX)]
        );
        $executor->expects(self::any())->method('socketBag')->willReturn($socketBag);

        return $executor;
    }

    /**
     * Return event types
     *
     * @return string[]
     */
    private function getEventTypes()
    {
        $ref        = new \ReflectionClass('AsyncSockets\Event\EventType');
        $eventTypes = [ ];
        foreach ($ref->getConstants() as $value) {
            $eventTypes[] = $value;
        }

        return $eventTypes;
    }
}
