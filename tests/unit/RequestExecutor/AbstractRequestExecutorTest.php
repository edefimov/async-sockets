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
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\NativeRequestExecutor;
use AsyncSockets\RequestExecutor\NoLimitationSolver;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class RequestExecutorTest
 */
abstract class AbstractRequestExecutorTest extends AbstractTestCase
{
    /**
     * List of test objects
     *
     * @var SocketInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $socket;

    /**
     * Socket resource
     *
     * @var resource
     */
    protected $socketResource;

    /**
     * RequestExecutor
     *
     * @var RequestExecutorInterface
     */
    protected $executor;

    /**
     * Create RequestExecutor for tests
     *
     * @return RequestExecutorInterface
     */
    abstract protected function createRequestExecutor();

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $isOpened             = false;
        $this->socketResource = fopen('php://temp', 'r+');
        $this->socket         = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [ ],
            '',
            false,
            true,
            true,
            [ 'getStreamResource', 'read', 'open', 'close' ]
        );
        $this->socket->expects(self::any())->method('getStreamResource')->willReturnCallback(
            function () use (&$isOpened) {
                return $isOpened ? $this->socketResource : null;
            }
        );
        $this->socket->expects(self::any())->method('open')->willReturnCallback(
            function () use (&$isOpened) {
                $isOpened = true;
            }
        );

        $this->socket->expects(self::any())->method('read')->willReturnCallback(function () {
            $mock = $this->getMockForAbstractClass(
                'AsyncSockets\Frame\FrameInterface',
                [ ],
                '',
                false,
                true,
                true,
                [ 'getData', '__toString' ]
            );

            $mock->expects(self::any())->method('getData')->willReturn('');
            $mock->expects(self::any())->method('__toString')->willReturn('');
            return $mock;
        });
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
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_sendto')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
    }

    /**
     * testExceptionOnMethodCall
     *
     * @param OperationInterface $operation Operation to test
     * @param string             $method Method to test exception
     *
     * @return void
     * @dataProvider socketMethodDataProvider
     */
    public function testExceptionOnMethodCall(OperationInterface $operation, $method)
    {
        $code = mt_rand(1, PHP_INT_MAX);
        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [ ],
            '',
            true,
            true,
            true,
            array_unique([ $method, 'createSocketResource', 'createIoInterface', 'isConnected', 'read', 'write' ])
        );

        $mock->expects(self::any())->method('isConnected')->willReturn($method === 'close');
        $mock->expects(self::any())->method('read')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FrameInterface')
        );
        $mock->expects(self::any())->method('createIoInterface')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\Io\IoInterface')
        );
        if ($method !== 'close') {
            $mock
                ->expects(self::once())
                ->method($method)
                ->willThrowException(new NetworkSocketException($mock, 'Test', $code));
        } else {
            if ($operation instanceof  WriteOperation) {
                $mock->expects(self::any())->method('write')->willReturnCallback(
                    function () use ($operation) {
                        return strlen($operation->getData());
                    }
                );
            }

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

        $this->executor->socketBag()->addSocket($mock, $operation);
        $this->executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::WRITE     => function (WriteEvent $event) {
                        $event->getOperation()->setData('I will pass the test');
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
     * @param OperationInterface $operation Operation to test
     * @param string             $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testNextOperationNotRequired(OperationInterface $operation, $eventType)
    {
        $oppositeEvent = [
            EventType::READ  => EventType::WRITE,
            EventType::WRITE => EventType::READ,
        ];

        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS   => 'php://temp',
            ],
            new CallbackEventHandler(
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
     * @param OperationInterface $operation Operation to test
     * @param string             $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testStopRequest(OperationInterface $operation, $eventType)
    {
        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(2))->method('count');

        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS   => 'php://temp',
            ],
            new CallbackEventHandler(
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
     * @param OperationInterface $operation Operation to test
     * @param string             $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testCancelSocketRequest(OperationInterface $operation, $eventType)
    {
        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(6))->method('count');

        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS   => 'php://temp',
            ],
            new CallbackEventHandler(
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
        $this->executor->socketBag()->addSocket(
            $clone,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS   => 'php://temp',
            ],
            new CallbackEventHandler(
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
     * @param OperationInterface $operation Operation to test
     * @param string             $eventType Event type for operation
     *
     * @return void
     * @expectedException \BadMethodCallException
     * @dataProvider socketOperationDataProvider
     */
    public function testSetLimitationDeciderOnExecute(OperationInterface $operation, $eventType)
    {
        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS   => 'php://temp',
            ],
            new CallbackEventHandler(
                [
                    EventType::INITIALIZE   => function (Event $event) {
                        $event->getExecutor()->withLimitationSolver(new NoLimitationSolver());
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
     * @param OperationInterface $operation Operation to test
     * @param string             $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \BadMethodCallException
     */
    public function testCantExecuteTwice(OperationInterface$operation, $eventType)
    {
        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS   => 'php://temp',
            ],
            new CallbackEventHandler(
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
     * @param OperationInterface $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends      testStopRequest
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 354
     */
    public function testPassingStreamContextHandle(OperationInterface $operation)
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


        /** @var SocketInterface $mock */
        $this->executor->socketBag()->addSocket(
            $mock,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS               => 'php://temp',
                NativeRequestExecutor::META_SOCKET_STREAM_CONTEXT => $streamContextHandle,
            ]
        );

        $this->executor->executeRequest();
        fclose($socketStreamResource);
    }

    /**
     * testEventFireSequence
     *
     * @param OperationInterface $operation Operation to execute
     * @param string             $eventType Event type
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testEventFireSequence(OperationInterface $operation, $eventType)
    {
        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS               => 'php://temp',
                NativeRequestExecutor::META_SOCKET_STREAM_CONTEXT => [
                    'options' => [ ],
                    'params'  => [ ],
                ],
            ]
        );

        $mock = $this->getMock('Countable', [ 'count' ]);

        /** @var \Countable|\PHPUnit_Framework_MockObject_MockObject $mock */
        $handlers = [
            EventType::INITIALIZE   => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 1);
                $meta = $this->executor->socketBag()->getSocketMetaData($this->socket);
                self::assertNull(
                    $meta[ NativeRequestExecutor::META_CONNECTION_START_TIME ],
                    'Connection start time must be null at this point'
                );
                self::assertNull(
                    $meta[ NativeRequestExecutor::META_CONNECTION_FINISH_TIME ],
                    'Connection finish time must be null at this point'
                );
                self::assertNull(
                    $meta[ NativeRequestExecutor::META_LAST_IO_START_TIME ],
                    'Last io time must be null at this point'
                );
            },
            EventType::CONNECTED    => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 2);
                $meta = $this->executor->socketBag()->getSocketMetaData($this->socket);
                self::assertNotNull(
                    $meta[ NativeRequestExecutor::META_CONNECTION_START_TIME ],
                    'Connection start time must not be null at this point'
                );
                self::assertNotNull(
                    $meta[ NativeRequestExecutor::META_CONNECTION_FINISH_TIME ],
                    'Connection finish time must not be null at this point'
                );
                self::assertNull(
                    $meta[ NativeRequestExecutor::META_LAST_IO_START_TIME ],
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

        $this->executor->withEventHandler(new CallbackEventHandler($handlers));

        $this->executor->executeRequest();
    }

    /**
     * testLimitationDecider
     *
     * @param OperationInterface $operation Operation to execute
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \LogicException
     */
    public function testLimitationDecider(OperationInterface $operation)
    {
        for ($i = 0; $i < 10; $i++) {
            $this->executor->socketBag()->addSocket(
                !$i ? $this->socket : clone $this->socket,
                $operation,
                [
                    NativeRequestExecutor::META_ADDRESS => 'php://temp',
                ]
            );
        }

        $decider = $this->getMock('AsyncSockets\RequestExecutor\NoLimitationSolver', [ 'decide' ]);
        $decider->expects(self::any())
            ->method('decide')
            ->willReturnOnConsecutiveCalls(
                LimitationSolverInterface::DECISION_OK,
                LimitationSolverInterface::DECISION_SKIP_CURRENT,
                LimitationSolverInterface::DECISION_PROCESS_SCHEDULED,
                LimitationSolverInterface::DECISION_OK,
                mt_rand(200, 500)
            );

        /** @var \AsyncSockets\RequestExecutor\NoLimitationSolver $decider */
        $this->executor->withLimitationSolver($decider);
        $this->executor->executeRequest();
    }

    /**
     * testTimeoutOnConnect
     *
     * @param OperationInterface $operation Operation to execute
     * @param string             $eventType Event type
     *
     * @return void
     * @depends      testEventFireSequence
     * @dataProvider socketOperationDataProvider
     */
    public function testTimeoutOnConnect(OperationInterface $operation, $eventType)
    {
        $this->prepareFor(__FUNCTION__);

        $timeGenerator = function () {
            static $time = 0;

            return $time++;
        };

        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

        $failHandler = function (Event $event) {
            self::fail($event->getType() . ' mustn\'t have been called');
        };

        $context = sha1(microtime(true));
        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutorInterface::META_ADDRESS            => 'php://temp',
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => 1,
                RequestExecutorInterface::META_USER_CONTEXT       => $context,
            ]
        );

        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(3))
            ->method('count');

        $this->executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::INITIALIZE   => [ $mock, 'count' ],
                    EventType::CONNECTED    => $failHandler,
                    $eventType              => $failHandler,
                    EventType::DISCONNECTED => $failHandler,
                    EventType::FINALIZE     => [ $mock, 'count' ],
                    EventType::TIMEOUT      => [
                        [ $mock, 'count' ],
                        function (Event $event) use ($context) {
                            self::assertSame($context, $event->getContext(), 'Incorect context');
                        }
                    ]
                ]
            )
        );
        $this->executor->executeRequest();
    }

    /**
     * testTimeoutOnIo
     *
     * @param OperationInterface $operation Operation to execute
     * @param string             $eventType Event type
     *
     * @return void
     * @depends      testTimeoutOnConnect
     * @dataProvider socketOperationDataProvider
     */
    public function testTimeoutOnIo(OperationInterface $operation, $eventType)
    {
        $timeGenerator = function () {
            static $time = 0;

            return $time++;
        };

        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                NativeRequestExecutor::META_ADDRESS    => 'php://temp',
                NativeRequestExecutor::META_IO_TIMEOUT => 1,
            ]
        );

        $mock = $this->getMock('Countable', [ 'count' ]);
        $mock->expects(self::exactly(5))
            ->method('count');

        $this->executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::INITIALIZE   => [ $mock, 'count' ],
                    EventType::CONNECTED    => [ $mock, 'count' ],
                    $eventType              => function (IoEvent $event) {
                        $this->prepareFor('testTimeoutOnIo');

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
     * @param string             $eventType Event type to throw exception in
     * @param OperationInterface $operation Operation to start
     *
     * @return void
     * @depends      testTimeoutOnConnect
     * @dataProvider eventTypeDataProvider
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 200
     */
    public function testThrowsNonSocketExceptionInEvent($eventType, OperationInterface $operation)
    {
        $meta = [
            NativeRequestExecutor::META_ADDRESS   => 'php://temp',
        ];

        $this->prepareFor(__FUNCTION__, $eventType, $operation);

        if ($eventType === EventType::TIMEOUT) {
            $timeGenerator = function () {
                static $time = 0;

                return $time++;
            };

            PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

            $meta[ NativeRequestExecutor::META_CONNECTION_TIMEOUT ] = 1;
        } elseif ($eventType === EventType::EXCEPTION) {
            $this->socket->expects(self::any())->method('open')->willThrowException(
                new SocketException('Test passed')
            );
        }

        $this->executor->socketBag()->addSocket($this->getSocketForEventType($eventType), $operation, $meta);

        $handler = function (Event $event) use ($eventType) {
            $readWriteTypes = [ EventType::READ, EventType::WRITE ];
            $throwException = $event->getType() === $eventType ||
                              (in_array($eventType, $readWriteTypes, true) &&
                               in_array($event->getType(), $readWriteTypes, true));
            if ($throwException) {
                throw new \RuntimeException('Test passed', 200);
            }
        };

        $this->executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::INITIALIZE   => $handler,
                    EventType::CONNECTED    => $handler,
                    EventType::ACCEPT       => $handler,
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
     * @param string             $eventType Event type to throw exception in
     * @param OperationInterface $operation Operation to start
     *
     * @return void
     * @depends      testTimeoutOnConnect
     * @dataProvider eventTypeDataProvider
     */
    public function testThrowingSocketExceptionsInEvent($eventType, OperationInterface $operation)
    {
        $meta = [
            NativeRequestExecutor::META_ADDRESS => 'php://temp',
        ];

        $this->prepareFor(__FUNCTION__, $eventType, $operation);

        if ($eventType === EventType::TIMEOUT) {
            $timeGenerator = function () {
                static $time = 0;

                return $time++;
            };

            PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

            $meta[ NativeRequestExecutor::META_CONNECTION_TIMEOUT ] = 1;
        } elseif ($eventType === EventType::EXCEPTION) {
            $this->socket->expects(self::any())->method('open')->willThrowException(
                new SocketException('Test passed')
            );
        }

        $this->executor->socketBag()->addSocket($this->getSocketForEventType($eventType), $operation, $meta);

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

        $this->executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::INITIALIZE   => $handler,
                    EventType::CONNECTED    => $handler,
                    EventType::ACCEPT       => $handler,
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
     * @param OperationInterface $operation Operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testLimitationDeciderEventsAreInvoked(OperationInterface $operation)
    {
        $mock = $this->getMock(
            'AsyncSockets\RequestExecutor\ConstantLimitationSolver',
            [ 'decide', 'invokeEvent'],
            [ mt_rand(1, PHP_INT_MAX) ]
        );


        $mock->expects(self::exactly(5)) // init, connected, operation, disconnect, finalize
            ->method('invokeEvent');
        $mock->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);

        /** @var \AsyncSockets\RequestExecutor\ConstantLimitationSolver $mock */
        $this->executor->withLimitationSolver($mock);
        $this->executor->socketBag()->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutorInterface::META_ADDRESS => 'php://temp',
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
            [ new ReadOperation(), EventType::READ ],
            [ new WriteOperation(), EventType::WRITE ],
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
            [ new ReadOperation(), 'open' ],
            [ new ReadOperation(), 'read' ],
            [ new ReadOperation(), 'close' ],
            [ new WriteOperation(), 'open' ],
            [ new WriteOperation(), 'write' ],
            [ new WriteOperation(), 'close' ],
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
                if ($value === EventType::DATA_ARRIVED) {
                    continue;
                }

                $result[] = [ $value, new ReadOperation() ];
                if ($value !== EventType::ACCEPT) {
                    $result[] = [ $value, new WriteOperation() ];
                }
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

    /**
     * Return socket object for given event type
     *
     * @param string $eventType Event type
     *
     * @return SocketInterface
     */
    protected function getSocketForEventType($eventType)
    {
        switch ($eventType) {
            case EventType::ACCEPT:
                $mock = $this->getMock(
                    'AsyncSockets\Socket\ServerSocket',
                    ['read', 'createSocketResource' , 'write', 'createIoInterface']
                );

                $mock->expects(self::any())->method('createSocketResource')->willReturnCallback(
                    function () {
                        return fopen('php://temp', 'rw');
                    }
                );
                $mock->expects(self::any())->method('createIoInterface')->willReturnCallback(
                    function () {
                        return $this->getMockForAbstractClass('AsyncSockets\Socket\Io\IoInterface');
                    }
                );
                $mock->expects(self::any())->method('read')->willReturnCallback(
                    function () {
                        $mock = $this->getMock(
                            'AsyncSockets\Frame\AcceptedFrame',
                            [ 'getClientSocket', 'getClientAddress' ],
                            [ ],
                            '',
                            false
                        );

                        $mock->expects(self::any())->method('getClientAddress')->willReturn('127.0.0.1:11111');
                        $mock->expects(self::any())
                             ->method('getClientSocket')
                             ->willReturn($this->getMock('AsyncSockets\Socket\ClientSocket'));

                        return $mock;
                    }
                );
                return $mock;
            default:
                return $this->socket;
        }
    }
}
