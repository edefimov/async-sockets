<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class CallbackEventHandlerTest
 */
class CallbackEventHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Handler bag
     *
     * @var CallbackEventHandler
     */
    private $bag;

    /**
     * Request executor
     *
     * @var RequestExecutorInterface
     */
    private $executor;

    /**
     * Socket
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * Execution context
     *
     * @var ExecutionContext
     */
    private $context;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->bag      = new CallbackEventHandler();
        $this->executor = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                               ->getMockForAbstractClass();
        $this->socket   = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
                               ->getMockForAbstractClass();
        $this->context  = new ExecutionContext();
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

        $this->bag->invokeEvent($event, $this->executor, $this->socket, $this->context);
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
            $this->createEventMock('test'),
            $this->executor,
            $this->socket,
            $this->context
        );
        $this->bag->invokeEvent(
            $this->createEventMock('keep'),
            $this->executor,
            $this->socket,
            $this->context
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
            $this->createEventMock('test'),
            $this->executor,
            $this->socket,
            $this->context
        );
        $this->bag->invokeEvent(
            $this->createEventMock('keep'),
            $this->executor,
            $this->socket,
            $this->context
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
            $this->createEventMock('test'),
            $this->executor,
            $this->socket,
            $this->context
        );
        $this->bag->invokeEvent(
            $this->createEventMock('keep'),
            $this->executor,
            $this->socket,
            $this->context
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
        $mock   = $this->getMockBuilder('Countable')->setMethods(['count'])->getMockForAbstractClass();
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
            $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                    ->getMockForAbstractClass(),
            $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
                    ->getMockForAbstractClass(),
            null,
            $eventType
        );

        return $event;
    }
}
