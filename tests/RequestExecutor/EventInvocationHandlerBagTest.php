<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\RequestExecutor\EventInvocationHandlerBag;
use AsyncSockets\RequestExecutor\Metadata\HandlerBag;

/**
 * Class EventInvocationHandlerBagTest
 */
class EventInvocationHandlerBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Handler bag
     *
     * @var EventInvocationHandlerBag
     */
    private $bag;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->bag = new EventInvocationHandlerBag();
    }

    /**
     * testEmpty
     *
     * @return void
     */
    public function testEmpty()
    {
        self::assertCount(0, $this->bag);
    }

    /**
     * testAddHandler
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @dataProvider callableDataProvider
     */
    public function testAddHandler($callables)
    {
        $event = $this->createEventMock('test');

        $this->bag->addHandler(
            [
                $event->getType() => $this->getMockedHandlers($callables, null)
            ]
        );

        $this->bag->invokeEvent($event);
    }

    /**
     * testRemoveHandler
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @depends testAddHandler
     * @dataProvider callableDataProvider
     */
    public function testRemoveHandler($callables)
    {
        $testCallables = $this->getMockedHandlers($callables, 0);
        $this->bag->addHandler(
            [
                'test' => $testCallables,
                'keep' => $this->getMockedHandlers($callables, null)
            ]
        );

        $this->bag->removeHandler(['test' => $testCallables]);
        $this->bag->invokeEvent(
            $this->createEventMock('test')
        );
        $this->bag->invokeEvent(
            $this->createEventMock('keep')
        );
    }

    /**
     * testRemoveAll
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @depends testAddHandler
     * @dataProvider callableDataProvider
     */
    public function testRemoveAll($callables)
    {
        $this->bag->addHandler(
            [
                'test' => $this->getMockedHandlers($callables, 0),
                'keep' => $this->getMockedHandlers($callables, 0)
            ]
        );

        $this->bag->removeAll();

        $this->bag->invokeEvent(
            $this->createEventMock('test')
        );
        $this->bag->invokeEvent(
            $this->createEventMock('keep')
        );
    }

    /**
     * testRemoveForEvent
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @depends testAddHandler
     * @dataProvider callableDataProvider
     */
    public function testRemoveForEvent($callables)
    {
        $this->bag->addHandler(
            [
                'test' => $this->getMockedHandlers($callables, 0),
                'keep' => $this->getMockedHandlers($callables, null)
            ]
        );

        $this->bag->removeForEvent('test');
        $this->bag->invokeEvent(
            $this->createEventMock('test')
        );
        $this->bag->invokeEvent(
            $this->createEventMock('keep')
        );
    }

    /**
     * testRemoveFromEmpty
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @depends testAddHandler
     * @dataProvider callableDataProvider
     */
    public function testRemoveFromEmpty($callables)
    {
        $this->bag->removeHandler(
            [
                md5(microtime()) => $callables
            ]
        );
    }

    /**
     * callableDataProvider
     *
     * @return array
     */
    public function callableDataProvider()
    {
        $func = function () {

        };
        return [
            [ $func ],
            [ [$func, clone $func ] ]
        ];
    }

    /**
     * Convert given list of callables to mocked ones
     *
     * @param callable|callable[] $callables Array of callables
     * @param int|null            $amountOfCalls Excpected amount of calls
     *
     * @return callable|callable[]
     */
    private function getMockedHandlers($callables, $amountOfCalls = null)
    {
        $mock   = $this->getMock('Countable', ['count']);
        $result = null;

        if (is_callable($callables)) {
            $count  = 1;
            $result = [$mock, 'count'];
        } else {
            $count = count($callables);
            $result = array_map(
                function () use ($mock) {
                    return [ $mock, 'count' ];
                },
                $callables
            );
        }

        if ($amountOfCalls !== null) {
            $count = $amountOfCalls;
        }

        $mock->expects(self::exactly($count))->method('count');
        return $result;
    }

    /**
     * Create event mock
     *
     * @param string $eventType Event type name
     *
     * @return Event
     */
    private function createEventMock($eventType)
    {
        $event = new Event(
            $this->getMock('AsyncSockets\RequestExecutor\RequestExecutorInterface'),
            $this->getMock('AsyncSockets\Socket\SocketInterface'),
            null,
            $eventType
        );

        return $event;
    }
}
