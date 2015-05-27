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
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\EventInvocationHandlerBag;
use AsyncSockets\RequestExecutor\LimitationDeciderInterface;
use AsyncSockets\RequestExecutor\NoLimitationDecider;
use AsyncSockets\RequestExecutor\RequestExecutor;
use AsyncSockets\Socket\SocketInterface;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;
use Tests\AsyncSockets\Socket\FileSocket;

/**
 * Class RequestExecutorTest
 */
class RequestExecutorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * List of test objects
     *
     * @var FileSocket
     */
    protected $socket;

    /**
     * RequestExecutor
     *
     * @var RequestExecutor
     */
    protected $executor;

    /**
     * Create RequestExecutor for tests
     *
     * @return RequestExecutor
     */
    protected function createRequestExecutor()
    {
        return new RequestExecutor();
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket   = new FileSocket();
        $this->executor = $this->createRequestExecutor();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(function () {
            return '';
        });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->setCallable(function ($handle, $data) {
            return strlen($data);
        });

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(function () {
            return 'php://temp';
        });
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('time')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
    }



    /**
     * testRemoveHandler
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testRemoveHandler($operation)
    {
        $handler = function () {
            self::fail('Handler is not removed');
        };

        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ]
        );

        $bag = new EventInvocationHandlerBag(
            [
                EventType::INITIALIZE => $handler,
            ]
        );
        $bag->removeHandler(
            [
                EventType::INITIALIZE => $handler,
                EventType::READ       => $handler,
            ]
        );

        $this->executor->setEventInvocationHandler($bag);
        $this->executor->executeRequest();
    }

    /**
     * testExceptionOnMethodCall
     *
     * @param string $operation Operation to test
     * @param string $method Method to test exception
     *
     * @return void
     * @dataProvider socketMethodDataProvider
     * @depends      testRemoveHandler
     */
    public function testExceptionOnMethodCall($operation, $method)
    {
        $code = mt_rand(1, PHP_INT_MAX);
        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [ ],
            '',
            true,
            true,
            true,
            [ $method, 'createSocketResource' ]
        );

        if ($method !== 'close') {
            $mock
                ->expects(self::once())
                ->method($method)
                ->willThrowException(new NetworkSocketException($mock, 'Test', $code));
        } else {
            $mock
                ->expects(self::any())
                ->method($method)
                ->willReturnCallback(
                    function () use ($mock, $code) {
                        /** @var SocketInterface $mock */
                        if ($mock->getStreamResource()) {
                            throw new NetworkSocketException($mock, 'Test', $code);
                        }
                    }
                );
        }


        $mock->expects(self::any())->method('createSocketResource')->willReturnCallback(
            function () {
                return fopen('php://temp', 'rw');
            }
        );

        $this->executor->getSocketBag()->addSocket($mock, [ RequestExecutor::META_OPERATION => $operation ]);
        $this->executor->setEventInvocationHandler(
            new EventInvocationHandlerBag(
                [
                    EventType::WRITE     => function (WriteEvent $event) {
                        $event->setData('I will pass the test');
                    },
                    EventType::EXCEPTION => function (SocketExceptionEvent $event) use ($code, $mock) {
                        $socketException = $event->getException();
                        self::assertInstanceOf('AsyncSockets\Exception\NetworkSocketException', $socketException);
                        /** @var NetworkSocketException $socketException */
                        self::assertEquals('Test', $socketException->getMessage());
                        self::assertEquals($code, $socketException->getCode());
                        self::assertSame($mock, $socketException->getSocket());
                    },
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * testNextOperationNotRequired
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends      testRemoveHandler
     */
    public function testNextOperationNotRequired($operation, $eventType)
    {
        $oppositeEvent = [
            EventType::READ  => EventType::WRITE,
            EventType::WRITE => EventType::READ,
        ];

        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ],
            new EventInvocationHandlerBag(
                [
                    $eventType                   => function (IoEvent $event) {
                        if ($event->getType() === EventType::READ) {
                            $event->nextIsWrite();
                        } else {
                            $event->nextIsRead();
                        }

                        $event->nextOperationNotRequired();
                    },
                    $oppositeEvent[ $eventType ] => function () {
                        self::fail('Io operation was not cancelled');
                    },
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * testStopRequest
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testStopRequest($operation, $eventType)
    {
        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(2))->method('count');

        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ],
            new EventInvocationHandlerBag(
                [
                    EventType::CONNECTED    => function (Event $event) {
                        $event->getExecutor()->stopRequest();
                    },
                    $eventType              => $failHandler,
                    EventType::DISCONNECTED => [ $mock, 'count' ],
                    EventType::FINALIZE     => [ $mock, 'count' ],
                    EventType::EXCEPTION    => $failHandler,
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * testCancelSocketRequest
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testCancelSocketRequest($operation, $eventType)
    {
        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(6))->method('count');

        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ],
            new EventInvocationHandlerBag(
                [
                    EventType::CONNECTED    => function (Event $event) {
                        $event->cancelThisOperation(true);
                    },
                    $eventType              => $failHandler,
                    EventType::DISCONNECTED => [ $mock, 'count' ],
                    EventType::FINALIZE     => [ $mock, 'count' ],
                    EventType::EXCEPTION    => $failHandler,
                ]
            )
        );

        $clone = clone $this->socket;
        $this->executor->getSocketBag()->addSocket(
            $clone,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ],
            new EventInvocationHandlerBag(
                [
                    EventType::CONNECTED    => [ $mock, 'count' ],
                    $eventType              => [ $mock, 'count' ],
                    EventType::DISCONNECTED => [ $mock, 'count' ],
                    EventType::FINALIZE     => [ $mock, 'count' ],
                    EventType::EXCEPTION    => $failHandler,
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * testSetLimitationDeciderOnExecute
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @expectedException \BadMethodCallException
     * @dataProvider socketOperationDataProvider
     */
    public function testSetLimitationDeciderOnExecute($operation, $eventType)
    {
        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ],
            new EventInvocationHandlerBag(
                [
                    EventType::INITIALIZE   => function (Event $event) {
                        $event->getExecutor()->setLimitationDecider(new NoLimitationDecider());
                    },
                    $eventType              => $failHandler,
                    EventType::DISCONNECTED => $failHandler,
                    EventType::FINALIZE     => $failHandler,
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * testStopRequestNonExecuting
     *
     * @return void
     * @expectedException \BadMethodCallException
     */
    public function testStopRequestNonExecuting()
    {
        $this->executor->stopRequest();
    }

    /**
     * testCantExecuteTwice
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \LogicException
     */
    public function testCantExecuteTwice($operation, $eventType)
    {
        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => $operation,
            ],
            new EventInvocationHandlerBag(
                [
                    $eventType => function () {
                        $this->executor->executeRequest();
                    },
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * testPassingStreamContextHandle
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends      testStopRequest
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 354
     */
    public function testPassingStreamContextHandle($operation)
    {
        $streamContextHandle  = stream_context_create([ ]);
        $socketStreamResource = fopen('php://temp', 'rw');

        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [ ],
            '',
            true,
            true,
            true,
            [ 'open' ]
        );

        $mock
            ->expects(self::any())
            ->method('open')
            ->with('php://temp', $streamContextHandle)
            ->willThrowException(new \RuntimeException('Test passed', 354));


        $this->executor->getSocketBag()->addSocket(
            $mock,
            [
                RequestExecutor::META_ADDRESS               => 'php://temp',
                RequestExecutor::META_SOCKET_STREAM_CONTEXT => $streamContextHandle,
                RequestExecutor::META_OPERATION             => $operation,
            ]
        );

        $this->executor->executeRequest();
        fclose($socketStreamResource);
    }

    /**
     * testEventFireSequence
     *
     * @param string $operation Operation to execute
     * @param string $eventType Event type
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testEventFireSequence($operation, $eventType)
    {
        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS               => 'php://temp',
                RequestExecutor::META_OPERATION             => $operation,
                RequestExecutor::META_SOCKET_STREAM_CONTEXT => [
                    'options' => [ ],
                    'params'  => [ ],
                ],
            ]
        );

        $mock = $this->getMock('Countable', [ 'count' ]);

        $handlers = [
            EventType::INITIALIZE   => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 1);
                $meta = $this->executor->getSocketBag()->getSocketMetaData($this->socket);
                self::assertNull(
                    $meta[ RequestExecutor::META_CONNECTION_START_TIME ],
                    'Connection start time must be null at this point'
                );
                self::assertNull(
                    $meta[ RequestExecutor::META_CONNECTION_FINISH_TIME ],
                    'Connection finish time must be null at this point'
                );
                self::assertNull(
                    $meta[ RequestExecutor::META_LAST_IO_START_TIME ],
                    'Last io time must be null at this point'
                );
            },
            EventType::CONNECTED    => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 2);
                $meta = $this->executor->getSocketBag()->getSocketMetaData($this->socket);
                self::assertNotNull(
                    $meta[ RequestExecutor::META_CONNECTION_START_TIME ],
                    'Connection start time must not be null at this point'
                );
                self::assertNotNull(
                    $meta[ RequestExecutor::META_CONNECTION_FINISH_TIME ],
                    'Connection finish time must not be null at this point'
                );
                self::assertNull(
                    $meta[ RequestExecutor::META_LAST_IO_START_TIME ],
                    'Last io time must be null at this point'
                );
            },
            $eventType              => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 3);
                self::assertInstanceOf(
                    'AsyncSockets\Event\IoEvent',
                    $event,
                    'On ' . $event->getType() . ' event IoEvent object must be provided'
                );
            },
            EventType::DISCONNECTED => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 4);
            },
            EventType::FINALIZE     => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 5);
            },
        ];

        $mock->expects(self::exactly(count($handlers)))
            ->method('count')
            ->willReturnOnConsecutiveCalls(1, 2, 3, 4, 5);

        $this->executor->setEventInvocationHandler(new EventInvocationHandlerBag($handlers));

        $this->executor->executeRequest();
    }

    /**
     * testLimitationDecider
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \LogicException
     */
    public function testLimitationDecider($operation)
    {
        for ($i = 0; $i < 10; $i++) {
            $this->executor->getSocketBag()->addSocket(
                !$i ? $this->socket : clone $this->socket,
                [
                    RequestExecutor::META_ADDRESS   => 'php://temp',
                    RequestExecutor::META_OPERATION => $operation,
                ]
            );
        }

        $decider = $this->getMock('AsyncSockets\RequestExecutor\NoLimitationDecider', [ 'decide' ]);
        $decider->expects(self::any())
            ->method('decide')
            ->willReturnOnConsecutiveCalls(
                LimitationDeciderInterface::DECISION_OK,
                LimitationDeciderInterface::DECISION_SKIP_CURRENT,
                LimitationDeciderInterface::DECISION_PROCESS_SCHEDULED,
                LimitationDeciderInterface::DECISION_OK,
                mt_rand(200, 500)
            );

        $this->executor->setLimitationDecider($decider);
        $this->executor->executeRequest();
    }

    /**
     * testTimeoutOnConnect
     *
     * @param string $operation Operation to execute
     * @param string $eventType Event type
     *
     * @return void
     * @depends      testEventFireSequence
     * @dataProvider socketOperationDataProvider
     */
    public function testTimeoutOnConnect($operation, $eventType)
    {
        $timeGenerator = function () {
            static $time = 0;

            return $time++;
        };

        $failHandler = function (Event $event) {
            self::fail($event->getType() . ' mustn\'t have been called');
        };

        PhpFunctionMocker::getPhpFunctionMocker('time')->setCallable($timeGenerator);
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);
        $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $streamSelect->setCallable(
            function (array &$read = null, array &$write = null) {
                $read  = [ ];
                $write = [ ];

                return 0;
            }
        );


        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS            => 'php://temp',
                RequestExecutor::META_CONNECTION_TIMEOUT => 1,
                RequestExecutor::META_OPERATION          => $operation,
            ]
        );

        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(3))
            ->method('count');

        $this->executor->setEventInvocationHandler(
            new EventInvocationHandlerBag(
                [
                    EventType::INITIALIZE   => [ $mock, 'count' ],
                    EventType::CONNECTED    => $failHandler,
                    $eventType              => $failHandler,
                    EventType::DISCONNECTED => $failHandler,
                    EventType::FINALIZE     => [ $mock, 'count' ],
                    EventType::TIMEOUT      => [ $mock, 'count' ],
                ]
            )
        );
        $this->executor->executeRequest();
    }

    /**
     * testTimeoutOnIo
     *
     * @param string $operation Operation to execute
     * @param string $eventType Event type
     *
     * @return void
     * @depends      testTimeoutOnConnect
     * @dataProvider socketOperationDataProvider
     */
    public function testTimeoutOnIo($operation, $eventType)
    {
        $timeGenerator = function () {
            static $time = 0;

            return $time++;
        };

        PhpFunctionMocker::getPhpFunctionMocker('time')->setCallable($timeGenerator);
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS    => 'php://temp',
                RequestExecutor::META_IO_TIMEOUT => 1,
                RequestExecutor::META_OPERATION  => $operation,
            ]
        );

        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(5))
            ->method('count');

        $this->executor->setEventInvocationHandler(
            new EventInvocationHandlerBag(
                [
                    EventType::INITIALIZE   => [ $mock, 'count' ],
                    EventType::CONNECTED    => [ $mock, 'count' ],
                    $eventType              => function (IoEvent $event) {
                        $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
                        $streamSelect->setCallable(
                            function (array &$read = null, array &$write = null) {
                                $read  = [ ];
                                $write = [ ];

                                return 1;
                            }
                        );

                        if ($event->getType() === EventType::READ) {
                            $event->nextIsWrite();
                        } else {
                            $event->nextIsRead();
                        }
                    },
                    EventType::DISCONNECTED => [ $mock, 'count' ],
                    EventType::FINALIZE     => [ $mock, 'count' ],
                    EventType::TIMEOUT      => [ $mock, 'count' ],
                ]
            )
        );
        $this->executor->executeRequest();
    }

    /**
     * testThrowsNonSocketExceptionInEvent
     *
     * @param string $eventType Event type to throw exception in
     * @param string $operation Operation to start
     *
     * @return void
     * @depends      testTimeoutOnConnect
     * @dataProvider eventTypeDataProvider
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 200
     */
    public function testThrowsNonSocketExceptionInEvent($eventType, $operation)
    {
        $meta = [
            RequestExecutor::META_ADDRESS   => 'php://temp',
            RequestExecutor::META_OPERATION => $operation,
        ];

        if ($eventType === EventType::TIMEOUT || $eventType === EventType::EXCEPTION) {
            $timeGenerator = function () {
                static $time = 0;

                return $time++;
            };

            PhpFunctionMocker::getPhpFunctionMocker('time')->setCallable($timeGenerator);
            PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

            $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
            $streamSelect->setCallable(
                function (array &$read = null, array &$write = null) use ($eventType) {
                    $read  = [ ];
                    $write = [ ];

                    return $eventType === EventType::EXCEPTION ? false : 0;
                }
            );

            $meta[ RequestExecutor::META_CONNECTION_TIMEOUT ] = 1;
        }

        $this->executor->getSocketBag()->addSocket($this->socket, $meta);

        $handler = function (Event $event) use ($eventType) {
            $readWriteTypes = [ EventType::READ, EventType::WRITE ];
            $throwException = $event->getType() === $eventType ||
                              (in_array($eventType, $readWriteTypes, true) &&
                               in_array($event->getType(), $readWriteTypes, true));
            if ($throwException) {
                throw new \RuntimeException('Test passed', 200);
            }
        };

        $this->executor->setEventInvocationHandler(
            new EventInvocationHandlerBag(
                [
                    EventType::INITIALIZE   => $handler,
                    EventType::CONNECTED    => $handler,
                    EventType::READ         => $handler,
                    EventType::WRITE        => $handler,
                    EventType::DISCONNECTED => $handler,
                    EventType::FINALIZE     => $handler,
                    EventType::TIMEOUT      => $handler,
                    EventType::EXCEPTION    => $handler,
                ]
            )
        );
        $this->executor->executeRequest();
    }

    /**
     * testThrowingSocketExceptionsInEvent
     *
     * @param string $eventType Event type to throw exception in
     * @param string $operation Operation to start
     *
     * @return void
     * @depends      testTimeoutOnConnect
     * @dataProvider eventTypeDataProvider
     */
    public function testThrowingSocketExceptionsInEvent($eventType, $operation)
    {
        $meta = [
            RequestExecutor::META_ADDRESS   => 'php://temp',
            RequestExecutor::META_OPERATION => $operation,
        ];

        if ($eventType === EventType::TIMEOUT || $eventType === EventType::EXCEPTION) {
            $timeGenerator = function () {
                static $time = 0;

                return $time++;
            };

            PhpFunctionMocker::getPhpFunctionMocker('time')->setCallable($timeGenerator);
            PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

            $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
            $streamSelect->setCallable(
                function (array &$read = null, array &$write = null) use ($eventType) {
                    $read  = [ ];
                    $write = [ ];

                    return $eventType === EventType::EXCEPTION ? false : 0;
                }
            );

            $meta[ RequestExecutor::META_CONNECTION_TIMEOUT ] = 1;
        }

        $this->executor->getSocketBag()->addSocket($this->socket, $meta);

        $handler = function (Event $event) use ($eventType) {
            $readWriteTypes = [ EventType::READ, EventType::WRITE ];
            $throwException = $event->getType() === $eventType ||
                              (in_array($eventType, $readWriteTypes, true) &&
                               in_array($event->getType(), $readWriteTypes, true));
            if ($throwException) {
                throw new SocketException('Test passed', 200);
            }
        };

        $mock = $this->getMockBuilder('Countable')->setMethods([ 'count' ])->getMock();
        $mock->expects(self::once())
            ->method('count');

        $this->executor->setEventInvocationHandler(
            new EventInvocationHandlerBag(
                [
                    EventType::INITIALIZE   => $handler,
                    EventType::CONNECTED    => $handler,
                    EventType::READ         => $handler,
                    EventType::WRITE        => $handler,
                    EventType::DISCONNECTED => $handler,
                    EventType::FINALIZE     => $handler,
                    EventType::TIMEOUT      => $handler,
                    EventType::EXCEPTION    => [ $mock, 'count' ],
                ]
            )
        );
        $this->executor->executeRequest();
    }

    /**
     * testEventSubscribersAreSet
     *
     * @return void
     */
    public function testLimitationDeciderEventsAreInvoked()
    {
        $mock = $this->getMock(
            'AsyncSockets\RequestExecutor\ConstantLimitationDecider',
            [ 'decide', 'invokeEvent'],
            [ mt_rand(1, PHP_INT_MAX) ]
        );


        $mock->expects(self::exactly(5)) // init, connected, operation, disconnect, finalize
            ->method('invokeEvent');
        $mock->expects(self::any())->method('decide')->willReturn(LimitationDeciderInterface::DECISION_OK);

        $this->executor->setLimitationDecider($mock);
        $this->executor->getSocketBag()->addSocket(
            $this->socket,
            [
                RequestExecutor::META_ADDRESS   => 'php://temp',
                RequestExecutor::META_OPERATION => RequestExecutor::OPERATION_READ,
            ]
        );
        $this->executor->executeRequest();
    }

    /**
     * socketOperationDataProvider
     *
     * @return array
     */
    public function socketOperationDataProvider()
    {
        // form: operation, event
        return [
            [ RequestExecutor::OPERATION_READ, EventType::READ ],
            [ RequestExecutor::OPERATION_WRITE, EventType::WRITE ],
        ];
    }

    /**
     * socketMethodDataProvider
     *
     * @return array
     */
    public function socketMethodDataProvider()
    {
        return [
            [ RequestExecutor::OPERATION_READ, 'open' ],
            [ RequestExecutor::OPERATION_READ, 'read' ],
            [ RequestExecutor::OPERATION_READ, 'close' ],
            [ RequestExecutor::OPERATION_WRITE, 'open' ],
            [ RequestExecutor::OPERATION_WRITE, 'write' ],
            [ RequestExecutor::OPERATION_WRITE, 'close' ],
        ];
    }

    /**
     * eventTypeDataProvider
     *
     * @return array
     */
    public function eventTypeDataProvider()
    {
        static $result;

        if (!$result) {
            $ref    = new \ReflectionClass('AsyncSockets\Event\EventType');
            $result = [ ];
            foreach ($ref->getConstants() as $value) {
                $result[] = [ $value, RequestExecutor::OPERATION_READ ];
                $result[] = [ $value, RequestExecutor::OPERATION_WRITE ];
            }
        }

        return $result;
    }

    /**
     * verifyEvent
     *
     * @param Event      $event Event to test
     * @param \Countable $mock Mock object with sequence information
     * @param int        $sequence Sequence number of this event
     *
     * @return void
     */
    private function verifyEvent(
        Event $event,
        \Countable $mock,
        $sequence
    ) {
        self::assertEquals($sequence, count($mock), $event->getType() . ' must be fired ' . $sequence);
        self::assertSame($this->socket, $event->getSocket(), 'Strange socket provided');
        self::assertNotNull($event->getExecutor(), 'Request executor was not provided');
    }
}
