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
     * Amount of sockets to test
     */
    const COUNT_TEST_SOCKETS = 10;

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

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(function() {
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
     * testCantSetHandlerOnNonAddedSocket
     *
     * @return void
     * @expectedException \OutOfBoundsException
     */
    public function testCantSetHandlerOnNonAddedSocket()
    {
        $this->executor->addHandler([
            EventType::CONNECTED => function () {
            }
        ], $this->socket);
    }

    /**
     * testAddSocket
     *
     * @param string $operation Operation to add
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testCantSetHandlerOnNonAddedSocket
     */
    public function testAddSocket($operation)
    {
        $this->executor->addSocket($this->socket, $operation, []);
        $this->executor->addHandler([
            EventType::CONNECTED => function () {
            }
        ], $this->socket);
    }

    /**
     * testCantAddSameSocketTwice
     *
     * @return void
     * @depends testAddSocket
     * @expectedException \LogicException
     */
    public function testCantAddSameSocketTwice()
    {
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_READ, []);
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_WRITE, []);
    }

    /**
     * testHasSocket
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testHasSocket($operation)
    {
        $this->executor->addSocket($this->socket, $operation, []);
        self::assertTrue(
            $this->executor->hasSocket($this->socket),
            'hasSocket returned false for added socket'
        );

        self::assertFalse(
            $this->executor->hasSocket(new FileSocket()),
            'hasSocket returned true for not added socket'
        );
    }

    /**
     * testMetadataIsFilled
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testAddSocket
     */
    public function testMetadataIsFilled($operation)
    {
        $this->executor->addSocket($this->socket, $operation, []);
        $meta = $this->executor->getSocketMetaData($this->socket);
        $ref  = new \ReflectionClass($this->executor);
        foreach ($ref->getConstants() as $name => $value) {
            if (!preg_match('#META_.*?#', $name)) {
                continue;
            }

            self::assertArrayHasKey($value, $meta, "Metadata key {$name} is not defined in getSocketMetaData results");
        }
    }

    /**
     * testRemoveSocket
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testHasSocket
     * @depends testMetadataIsFilled
     */
    public function testRemoveSocket($operation)
    {
        $this->executor->addSocket($this->socket, $operation, []);
        $this->executor->removeSocket($this->socket);
        self::assertFalse(
            $this->executor->hasSocket($this->socket),
            'hasSocket returned true for removed socket'
        );
    }

    /**
     * testRemoveHandler
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testHasSocket
     * @depends testMetadataIsFilled
     */
    public function testRemoveHandler($operation)
    {
        $handler = function () {
            self::fail('Handler is not removed');
        };

        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp'
        ]);
        $this->executor->addHandler(
            [
                EventType::INITIALIZE => $handler
            ]
        );

        $this->executor->removeHandler(
            [
                EventType::INITIALIZE => $handler,
                EventType::READ       => $handler,
            ]
        );

        $this->executor->executeRequest();
    }

    /**
     * testRemoveHandlerOnNonExistingSocket
     *
     * @return void
     * @depends testHasSocket
     * @depends testMetadataIsFilled
     */
    public function testRemoveHandlerOnNonExistingSocket()
    {
        $this->executor->removeHandler(
            [
                EventType::INITIALIZE => function () {

                },
            ],
            $this->socket
        );
    }

    /**
     * testRemoveHandlerOnSocket
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testRemoveHandler
     */
    public function testRemoveHandlerOnSocket($operation)
    {
        $handler = function () {
            self::fail('Handler for socket is not removed');
        };

        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp'
        ]);
        $this->executor->addHandler(
            [
                EventType::INITIALIZE => $handler
            ],
            $this->socket
        );

        $this->executor->removeHandler(
            [
                EventType::INITIALIZE => $handler,
                EventType::READ       => $handler,
            ],
            $this->socket
        );

        $this->executor->executeRequest();
    }

    /**
     * testRemoveHandlerNotAffectOnOtherSocket
     *
     * @param string $operation Operation to test
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testRemoveHandler
     */
    public function testRemoveHandlerNotAffectOnOtherSocket($operation)
    {
        $fail = function () {
            self::fail('Handler for socket is not removed');
        };

        $mock = self::getMockBuilder('Countable')->setMethods(['count'])->getMock();
        $mock->expects(self::once())->method('count');

        $socket = new FileSocket();
        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp'
        ]);
        $this->executor->addSocket($socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp'
        ]);

        $this->executor->addHandler(
            [
                EventType::INITIALIZE => $fail
            ],
            $this->socket
        );
        $this->executor->addHandler(
            [
                EventType::INITIALIZE => function (Event $event) use ($socket, $mock) {
                    self::assertSame($socket, $event->getSocket());
                    /** @var \Countable $mock */
                    return count($mock);
                }
            ],
            $socket
        );

        $this->executor->removeHandler(
            [
                EventType::INITIALIZE => $fail,
            ],
            $this->socket
        );

        $this->executor->executeRequest();
    }

    /**
     * testNonAddedSocketRemove
     *
     * @return void
     * @depends testRemoveSocket
     */
    public function testNonAddedSocketRemove()
    {
        $this->executor->removeSocket($this->socket);
        self::assertFalse(
            $this->executor->hasSocket($this->socket),
            'hasSocket returned true for removed socket'
        );
    }

    /**
     * testExceptionOnMethodCall
     *
     * @param string $operation Operation to test
     * @param string $method Method to test exception
     *
     * @return void
     * @dataProvider socketMethodDataProvider
     * @depends testRemoveHandler
     */
    public function testExceptionOnMethodCall($operation, $method)
    {
        $code = mt_rand(1, PHP_INT_MAX);
        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [],
            '',
            true,
            true,
            true,
            [$method, 'createSocketResource']
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


        $mock->expects(self::any())->method('createSocketResource')->willReturnCallback(function () {
            return fopen('php://temp', 'rw');
        });

        $this->executor->addSocket($mock, $operation);
        $this->executor->addHandler(
            [
                EventType::WRITE => function (WriteEvent $event) {
                    $event->setData('I will pass the test');
                },
                EventType::EXCEPTION => function (SocketExceptionEvent $event) use ($code, $mock) {
                    $socketException = $event->getException();
                    self::assertInstanceOf('AsyncSockets\Exception\NetworkSocketException', $socketException);
                    /** @var NetworkSocketException $socketException */
                    self::assertEquals('Test', $socketException->getMessage());
                    self::assertEquals($code, $socketException->getCode());
                    self::assertSame($mock, $socketException->getSocket());
                }
            ]
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
     * @depends testRemoveHandler
     */
    public function testNextOperationNotRequired($operation, $eventType)
    {
        $this->executor->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp'
            ]
        );

        $oppositeEvent = [
            EventType::READ  => EventType::WRITE,
            EventType::WRITE => EventType::READ,
        ];

        $this->executor->addHandler(
            [
                $eventType => function (IoEvent $event) {
                    if ($event->getType() === EventType::READ) {
                        $event->nextIsWrite();
                    } else {
                        $event->nextIsRead();
                    }

                    $event->nextOperationNotRequired();
                },

                $oppositeEvent[$eventType] => function () {
                    self::fail('Io operation was not cancelled');
                }
            ],
            $this->socket
        );
        $this->executor->executeRequest();
    }

    /**
     * testCantRemoveSocketDuringExecute
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \LogicException
     */
    public function testCantRemoveSocketDuringExecute($operation, $eventType)
    {
        $this->executor->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp',
            ]
        );

        $this->executor->addHandler(
            [
                $eventType => function (Event $event) {
                    self::assertSame($this->socket, $event->getSocket(), 'Unknown socket was passed in event');
                    self::assertSame(
                        $this->executor,
                        $event->getExecutor(),
                        'Unexpected request executor is given in event'
                    );

                    $this->executor->removeSocket($event->getSocket());
                }
            ],
            $this->socket
        );
        $this->executor->executeRequest();

        self::fail('Event handler must have been executed');
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
        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp',
            ]
        );

        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::exactly(2))->method('count');

        $this->executor->addHandler(
            [
                EventType::CONNECTED => function (Event $event) {
                    $event->getExecutor()->stopRequest();
                },
                $eventType              => $failHandler,
                EventType::DISCONNECTED => [$mock, 'count'],
                EventType::FINALIZE     => [$mock, 'count'],
                EventType::EXCEPTION    => $failHandler,
            ],
            $this->socket
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
        $failHandler = function (Event $event) {
            self::fail('Event ' . $event->getType() . ' shouldn\'t have been fired');
        };

        $this->executor->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp',
            ]
        );

        $clone = clone $this->socket;
        $this->executor->addSocket(
            $clone,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp',
            ]
        );

        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::exactly(6))->method('count');

        $this->executor->addHandler(
            [
                EventType::CONNECTED => function (Event $event) {
                    $event->getExecutor()->cancelSocketRequest($event->getSocket());
                },
                $eventType              => $failHandler,
                EventType::DISCONNECTED => [$mock, 'count'],
                EventType::FINALIZE     => [$mock, 'count'],
                EventType::EXCEPTION    => $failHandler,
            ],
            $this->socket
        );

        $this->executor->addHandler(
            [
                EventType::CONNECTED    => [$mock, 'count'],
                $eventType              => [$mock, 'count'],
                EventType::DISCONNECTED => [$mock, 'count'],
                EventType::FINALIZE     => [$mock, 'count'],
                EventType::EXCEPTION    => $failHandler,
            ],
            $clone
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

        $this->executor->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp',
            ]
        );

        $this->executor->addHandler(
            [
                EventType::INITIALIZE => function (Event $event) {
                    $event->getExecutor()->setLimitationDecider(new NoLimitationDecider());
                },
                $eventType              => $failHandler,
                EventType::DISCONNECTED => $failHandler,
                EventType::FINALIZE     => $failHandler,
            ],
            $this->socket
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
     * testCancelSocketRequestNonExecuting
     *
     * @return void
     * @expectedException \BadMethodCallException
     */
    public function testCancelSocketRequestNonExecuting()
    {
        $this->executor->cancelSocketRequest($this->socket);
    }

    /**
     * testCantExecuteTwice
     *
     * @param string $operation Operation to test
     * @param string $eventType Event type for operation
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @depends testCantRemoveSocketDuringExecute
     * @expectedException \LogicException
     */
    public function testCantExecuteTwice($operation, $eventType)
    {
        $this->executor->addSocket(
            $this->socket,
            $operation,
            [
                RequestExecutor::META_ADDRESS => 'php://temp'
            ]
        );

        $this->executor->addHandler(
            [
                $eventType => function () {
                    $this->executor->executeRequest();
                }
            ],
            $this->socket
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
     * @depends testStopRequest
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 354
     */
    public function testPassingStreamContextHandle($operation)
    {
        $streamContextHandle  = stream_context_create([]);
        $socketStreamResource = fopen('php://temp', 'rw');

        $mock = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\AbstractSocket',
            [],
            '',
            true,
            true,
            true,
            ['open']
        );

        $mock
            ->expects(self::any())
            ->method('open')
            ->with('php://temp', $streamContextHandle)
            ->willThrowException(new \RuntimeException('Test passed', 354));





        $this->executor->addSocket(
            $mock,
            $operation,
            [
                RequestExecutor::META_ADDRESS               => 'php://temp',
                RequestExecutor::META_SOCKET_STREAM_CONTEXT => $streamContextHandle
            ]
        );

        $this->executor->executeRequest();
        fclose($socketStreamResource);
    }

    /**
     * testMetadataCanChange
     *
     * @param string $phpName Name in php file
     * @param string $key Key in metadata array
     * @param bool   $isReadOnly Flag whether it is read only constant
     * @param string $initialOperation Initial operation
     *
     * @return void
     * @dataProvider metadataKeysDataProvider
     */
    public function testMetadataCanChange($phpName, $key, $isReadOnly, $initialOperation)
    {
        $this->executor->addSocket($this->socket, $initialOperation, []);
        $originalMeta = $this->executor->getSocketMetaData($this->socket);
        self::assertEquals(
            $initialOperation,
            $originalMeta[RequestExecutor::META_OPERATION],
            'Unexpected initial operation'
        );

        $this->executor->setSocketMetaData($this->socket, $key, mt_rand(1, PHP_INT_MAX));
        $newMeta = $this->executor->getSocketMetaData($this->socket);
        if ($isReadOnly) {
            self::assertSame(
                $originalMeta[$key],
                $newMeta[$key],
                'Read-only metadata ' . $phpName . ' has been changed, but mustn\'t'
            );
        } else {
            self::assertNotSame(
                $originalMeta[$key],
                $newMeta[$key],
                'Writable value ' . $phpName . ' has not been modified, but must'
            );
        }

    }

    /**
     * testEventFireSequence
     *
     * @param string $operation Operation to execute
     * @param string $eventType Event type
     *
     * @return void
     * @depends testMetadataCanChange
     * @dataProvider socketOperationDataProvider
     */
    public function testEventFireSequence($operation, $eventType)
    {
        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS               => 'php://temp',
            RequestExecutor::META_SOCKET_STREAM_CONTEXT => [
                'options' => [ ],
                'params' => [ ]
            ],
        ]);

        $mock = $this->getMock('Countable', ['count']);

        $handlers = [
            EventType::INITIALIZE   => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 1);
                $meta = $this->executor->getSocketMetaData($this->socket);
                self::assertNull(
                    $meta[RequestExecutor::META_CONNECTION_START_TIME],
                    'Connection start time must be null at this point'
                );
                self::assertNull(
                    $meta[RequestExecutor::META_CONNECTION_FINISH_TIME],
                    'Connection finish time must be null at this point'
                );
                self::assertNull(
                    $meta[RequestExecutor::META_LAST_IO_START_TIME],
                    'Last io time must be null at this point'
                );
            },
            EventType::CONNECTED    => function (Event $event) use ($mock) {
                $this->verifyEvent($event, $mock, 2);
                $meta = $this->executor->getSocketMetaData($this->socket);
                self::assertNotNull(
                    $meta[RequestExecutor::META_CONNECTION_START_TIME],
                    'Connection start time must not be null at this point'
                );
                self::assertNotNull(
                    $meta[RequestExecutor::META_CONNECTION_FINISH_TIME],
                    'Connection finish time must not be null at this point'
                );
                self::assertNull(
                    $meta[RequestExecutor::META_LAST_IO_START_TIME],
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

        $this->executor->addHandler($handlers);

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
        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp',
        ]);
        $this->executor->addSocket(clone $this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp',
        ]);
        $this->executor->addSocket(clone $this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp',
        ]);

        $decider = $this->getMock('AsyncSockets\RequestExecutor\NoLimitationDecider', ['decide']);
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
     * @depends testEventFireSequence
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
        $streamSelect->setCallable(function (array &$read = null, array &$write = null) {
            $read  = [];
            $write = [];
            return 0;
        });


        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp',
            RequestExecutor::META_CONNECTION_TIMEOUT => 1
        ]);

        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::exactly(3))
            ->method('count');

        $this->executor->addHandler([
            EventType::INITIALIZE   => [$mock, 'count'],
            EventType::CONNECTED    => $failHandler,
            $eventType              => $failHandler,
            EventType::DISCONNECTED => $failHandler,
            EventType::FINALIZE     => [$mock, 'count'],
            EventType::TIMEOUT      => [$mock, 'count'],
        ]);
        $this->executor->executeRequest();
    }

    /**
     * testTimeoutOnIo
     *
     * @param string $operation Operation to execute
     * @param string $eventType Event type
     *
     * @return void
     * @depends testTimeoutOnConnect
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

        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp',
            RequestExecutor::META_IO_TIMEOUT => 1
        ]);

        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::exactly(5))
            ->method('count');

        $this->executor->addHandler([
            EventType::INITIALIZE   => [$mock, 'count'],
            EventType::CONNECTED    => [$mock, 'count'],
            $eventType              => function (IoEvent $event) {
                $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
                $streamSelect->setCallable(function (array &$read = null, array &$write = null) {
                    $read  = [];
                    $write = [];
                    return 1;
                });

                if ($event->getType() === EventType::READ) {
                    $event->nextIsWrite();
                } else {
                    $event->nextIsRead();
                }
            },
            EventType::DISCONNECTED => [$mock, 'count'],
            EventType::FINALIZE     => [$mock, 'count'],
            EventType::TIMEOUT      => [$mock, 'count'],
        ]);
        $this->executor->executeRequest();
    }

    /**
     * testThrowsNonSocketExceptionInEvent
     *
     * @param string $eventType Event type to throw exception in
     * @param string $operation Operation to start
     *
     * @return void
     * @depends testTimeoutOnConnect
     * @dataProvider eventTypeDataProvider
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 200
     */
    public function testThrowsNonSocketExceptionInEvent($eventType, $operation)
    {
        $meta = [
            RequestExecutor::META_ADDRESS => 'php://temp',
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

            $meta[RequestExecutor::META_CONNECTION_TIMEOUT] = 1;
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

        $this->executor->addHandler(
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
     * @depends testTimeoutOnConnect
     * @dataProvider eventTypeDataProvider
     */
    public function testThrowingSocketExceptionsInEvent($eventType, $operation)
    {
        $meta = [
            RequestExecutor::META_ADDRESS => 'php://temp',
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

            $meta[RequestExecutor::META_CONNECTION_TIMEOUT] = 1;
        }

        $this->executor->addSocket($this->socket, $operation, $meta);

        $handler = function (Event $event) use ($eventType) {
            $readWriteTypes = [EventType::READ, EventType::WRITE];
            $throwException = $event->getType() === $eventType ||
                              (in_array($eventType, $readWriteTypes, true) &&
                               in_array($event->getType(), $readWriteTypes, true));
            if ($throwException) {
                throw new SocketException('Test passed', 200);
            }
        };

        $mock = $this->getMockBuilder('Countable')->setMethods(['count'])->getMock();
        $mock->expects(self::once())
             ->method('count');

        $this->executor->addHandler(
            [
                EventType::INITIALIZE   => $handler,
                EventType::CONNECTED    => $handler,
                EventType::READ         => $handler,
                EventType::WRITE        => $handler,
                EventType::DISCONNECTED => $handler,
                EventType::FINALIZE     => $handler,
                EventType::TIMEOUT      => $handler,
                EventType::EXCEPTION    => [$mock, 'count'],
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
            [RequestExecutor::OPERATION_READ, EventType::READ],
            [RequestExecutor::OPERATION_WRITE, EventType::WRITE],
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
            [RequestExecutor::OPERATION_READ, 'open'],
            [RequestExecutor::OPERATION_READ, 'read'],
            [RequestExecutor::OPERATION_READ, 'close'],
            [RequestExecutor::OPERATION_WRITE, 'open'],
            [RequestExecutor::OPERATION_WRITE, 'write'],
            [RequestExecutor::OPERATION_WRITE, 'close'],
        ];
    }

    /**
     * metadataKeysDataProvider
     *
     * @return array
     */
    public function metadataKeysDataProvider()
    {
        static $metadata;
        if ($metadata === null) {
            $readOnlyKeys = [
                RequestExecutor::META_REQUEST_COMPLETE       => 1,
                RequestExecutor::META_CONNECTION_FINISH_TIME => 1,
                RequestExecutor::META_CONNECTION_START_TIME  => 1,
                RequestExecutor::META_LAST_IO_START_TIME     => 1,
            ];

            $metadata = [];
            $ref = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutor');
            foreach ($ref->getConstants() as $name => $value) {
                if (!preg_match('#META_.*?#', $name)) {
                    continue;
                }

                $metadata[] = [$name, $value, isset($readOnlyKeys[$value]), RequestExecutor::OPERATION_READ ];
                $metadata[] = [$name, $value, isset($readOnlyKeys[$value]), RequestExecutor::OPERATION_WRITE ];
            }
        }

        return $metadata;
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
            $result = [];
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
