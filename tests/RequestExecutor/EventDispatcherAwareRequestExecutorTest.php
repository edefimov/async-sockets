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
use AsyncSockets\Event\EventType;
use AsyncSockets\RequestExecutor\EventDispatcherAwareRequestExecutor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class EventDispatcherAwareRequestExecutorTest
 */
class EventDispatcherAwareRequestExecutorTest extends RequestExecutorTest
{
    /**
     * EventDispatcherAwareRequestExecutor
     *
     * @var EventDispatcherAwareRequestExecutor
     */
    protected $executor;

    /** {@inheritdoc} */
    protected function createRequestExecutor()
    {
        return new EventDispatcherAwareRequestExecutor();
    }

    /**
     * testThatAllEventsPassesThroughDispatcher
     *
     * @param string $eventType Event type
     * @param string $operation Operation to execute
     *
     * @return void
     * @dataProvider eventTypeDataProvider
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 200
     */
    public function testThatAllEventsPassesThroughDispatcher($eventType, $operation)
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            self::markTestSkipped('You must have symfony/event-dispatcher installed to pass this test');
        }

        $dispatcher = new EventDispatcher();
        $this->executor->setEventDispatcher($dispatcher);

        $meta = [
            RequestExecutorInterface::META_ADDRESS => 'php://temp',
        ];

        if ($eventType === EventType::TIMEOUT || $eventType === EventType::EXCEPTION) {
            $timeGenerator = function () {
                static $time = 0;
                return $time++;
            };

            PhpFunctionMocker::getPhpFunctionMocker('time')->setCallable($timeGenerator);
            PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

            $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
            $streamSelect->setCallable(function (array &$read = null, array &$write = null) use ($eventType) {
                $read  = [];
                $write = [];
                return $eventType === EventType::EXCEPTION ? false : 0;
            });

            $meta[RequestExecutorInterface::META_CONNECTION_TIMEOUT] = 1;
        }

        $this->executor->addSocket($this->socket, $operation, $meta);

        $handler = function (Event $event) use ($eventType) {
            $readWriteTypes = [EventType::READ, EventType::WRITE];
            $throwException = $event->getType() === $eventType ||
                              (in_array($eventType, $readWriteTypes, true) &&
                               in_array($event->getType(), $readWriteTypes, true));
            if ($throwException) {
                throw new \RuntimeException('Test passed', 200);
            }
        };

        $ref = new \ReflectionClass('AsyncSockets\Event\EventType');
        foreach ($ref->getConstants() as $value) {
            $dispatcher->addListener($value, $handler);
        }

        $this->executor->executeRequest();
    }
}
