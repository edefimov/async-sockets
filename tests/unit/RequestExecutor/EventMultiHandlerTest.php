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

use AsyncSockets\Event\Event;
use AsyncSockets\RequestExecutor\EventMultiHandler;

/**
 * Class EventMultiHandlerTest
 */
class EventMultiHandlerTest extends EventHandlerInterfaceTest
{
    /**
     * Test object
     *
     * @var EventMultiHandler
     */
    private $multiHandler;

    /**
     * testAddHandler
     *
     * @return void
     */
    public function testAddHandler()
    {
        $handler = $this->createMockedHandler();
        $event   = $this->createEventStub();

        $handler->expects(self::once())
            ->method('invokeEvent')
            ->with($event);

        $this->multiHandler->addHandler($handler);
        $this->multiHandler->invokeEvent($event);
    }

    /**
     * testAddHandlerInConstruct
     *
     * @return void
     */
    public function testAddHandlerInConstruct()
    {
        $handler = $this->createMockedHandler();
        $event   = $this->createEventStub();

        $handler->expects(self::once())
            ->method('invokeEvent')
            ->with($event);

        $multiHandler = new EventMultiHandler([$handler]);
        $multiHandler->invokeEvent($event);
    }

    /**
     * testHandlerIsAddedOnlyOnce
     *
     * @return void
     */
    public function testHandlerIsAddedOnlyOnce()
    {
        $handler = $this->createMockedHandler();
        $event   = $this->createEventStub();

        $handler->expects(self::once())
            ->method('invokeEvent')
            ->with($event);

        for ($i = 0; $i < 5; $i++) {
            $this->multiHandler->addHandler($handler);
        }

        $this->multiHandler->invokeEvent($event);
    }

    /**
     * testRemoveHandler
     *
     * @return void
     */
    public function testRemoveHandler()
    {
        $handler = $this->createMockedHandler();
        $event   = $this->createEventStub();

        $handler->expects(self::never())
                ->method('invokeEvent');

        $this->multiHandler->addHandler($handler);
        $this->multiHandler->removeHandler($handler);
        $this->multiHandler->invokeEvent($event);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->multiHandler = new EventMultiHandler();
    }

    /**
     * Create Event stub object
     *
     * @return Event
     */
    private function createEventStub()
    {
        return $this->getMock(
            'AsyncSockets\Event\Event',
            [],
            [],
            '',
            false
        );
    }
}
