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
use AsyncSockets\RequestExecutor\RequestExecutor;
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
    private $socket;

    /**
     * RequestExecutor
     *
     * @var RequestExecutor
     */
    private $executor;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket   = new FileSocket();
        $this->executor = new RequestExecutor(false);
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('time')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
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
                EventType::INITIALIZE => $handler,
                EventType::EXCEPTION  => function (SocketExceptionEvent $event) {
                    throw $event->getException();
                }
            ]
        );

        $this->executor->removeHandler(
            [
                EventType::INITIALIZE => $handler,
                EventType::READ       => $handler,
            ]
        );

        $this->executor->execute();
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
                RequestExecutor::META_ADDRESS => 'php://temp'
            ]
        );

        $this->executor->addHandler(
            [
                $eventType           => function (Event $event) {
                    self::assertSame($this->socket, $event->getSocket(), 'Unknown socket was passed in event');
                    self::assertSame(
                        $this->executor,
                        $event->getExecutor(),
                        'Unexpected request executor is given in event'
                    );

                    $this->executor->removeSocket($event->getSocket());
                },
                EventType::EXCEPTION => function (SocketExceptionEvent $e) {
                    throw $e->getException();
                },
            ],
            $this->socket
        );
        $this->executor->execute();

        self::fail('Event handler must have been executed');
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
     * @return void
     * @depends testMetadataCanChange
     * @dataProvider socketOperationDataProvider
     */
    public function testEventFireSequence($operation, $eventType)
    {
        $this->executor->addSocket($this->socket, $operation, [
            RequestExecutor::META_ADDRESS => 'php://temp'
        ]);

        $mock = $this->getMock('\Countable', ['count']);

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
                self::assertNull(
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
            EventType::EXCEPTION    => [$this, 'socketExceptionEventHandler'],
        ];

        $mock->expects(self::exactly(count($handlers) - 1))
            ->method('count')
            ->willReturnOnConsecutiveCalls(1, 2, 3, 4, 5);

        $this->executor->addHandler($handlers);

        $this->executor->execute();
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

        $mock = $this->getMock('\Countable', ['count']);
        $mock->expects(self::exactly(3))
            ->method('count');

        $this->executor->addHandler([
            EventType::INITIALIZE   => [$mock, 'count'],
            EventType::CONNECTED    => $failHandler,
            $eventType              => $failHandler,
            EventType::DISCONNECTED => $failHandler,
            EventType::FINALIZE     => [$mock, 'count'],
            EventType::TIMEOUT      => [$mock, 'count'],
            EventType::EXCEPTION    => [$this, 'socketExceptionEventHandler'],
        ]);
        $this->executor->execute();
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

        $mock = $this->getMock('\Countable', ['count']);
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
            EventType::EXCEPTION    => [$this, 'socketExceptionEventHandler'],
        ]);
        $this->executor->execute();
    }

    /**
     * testExceptionIsCaughtInEvent
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
    public function testExceptionIsCaughtInEvent($eventType, $operation)
    {
        $meta = [
            RequestExecutor::META_ADDRESS => 'php://temp',
        ];

        if ($eventType == EventType::TIMEOUT) {
            $timeGenerator = function () {
                static $time = 0;
                return $time++;
            };

            PhpFunctionMocker::getPhpFunctionMocker('time')->setCallable($timeGenerator);
            PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable($timeGenerator);

            $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
            $streamSelect->setCallable(function (array &$read = null, array &$write = null) {
                $read  = [];
                $write = [];
                return 0;
            });

            $meta[RequestExecutor::META_CONNECTION_TIMEOUT] = 1;
        }

        $this->executor->addSocket($this->socket, $operation, $meta);

        $handler = function (Event $event) use ($eventType) {
            $readWriteTypes = [EventType::READ, EventType::WRITE];
            $throwException = $event->getType() === $eventType ||
                              (in_array($eventType, $readWriteTypes) && in_array($event->getType(), $readWriteTypes));
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
                EventType::EXCEPTION    => function (SocketExceptionEvent $event) {
                    throw $event->getException();
                },
            ]
        );
        $this->executor->execute();
    }

    /**
     * testDoubleExceptionIsTurnedOff
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @depends testExceptionIsCaughtInEvent
     * @dataProvider socketOperationDataProvider
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 200
     */
    public function testDoubleExceptionIsTurnedOff($operation)
    {
        $executor = new RequestExecutor(false);
        $executor->addSocket($this->socket, $operation);
        $executor->addHandler([
            EventType::INITIALIZE => function () {
                throw new \RuntimeException('Test passed', 200);
            },
            EventType::EXCEPTION => function (SocketExceptionEvent $event) {
                self::assertNotInstanceOf(
                    'AsyncSockets\Event\SocketExceptionEvent',
                    $event->getOriginalEvent(),
                    'Double exception was thrown'
                );

                throw $event->getException();
            }
        ]);
        $executor->execute();
    }

    /**
     * testDoubleExceptionIsTurnedOn
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @depends testExceptionIsCaughtInEvent
     * @dataProvider socketOperationDataProvider
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test passed
     * @expectedExceptionCode 200
     */
    public function testDoubleExceptionIsTurnedOn($operation)
    {
        $testException       = new \LogicException('Failed to initialize', 150);
        $testSecondException = new \OutOfRangeException('Some error', 330);
        $executor            = new RequestExecutor(true);
        $executor->addSocket($this->socket, $operation);
        $executor->addHandler(
            [
                EventType::INITIALIZE => function () use ($testException) {
                    throw $testException;
                },
                EventType::EXCEPTION  => function (SocketExceptionEvent $event) use (
                    $testException,
                    $testSecondException
                ) {
                    $originalEvent = $event->getOriginalEvent();
                    if (!($originalEvent instanceof SocketExceptionEvent)) {
                        self::assertSame(
                            $testException,
                            $event->getException(),
                            'Strange exception is caught'
                        );

                        throw $testSecondException;
                    } else {
                        self::assertSame(
                            $testException,
                            $originalEvent->getException(),
                            'Strange original exception is caught'
                        );
                        self::assertSame(
                            $testSecondException,
                            $event->getException(),
                            'Strange second exception is caught'
                        );

                        throw new \RuntimeException('Test passed', 200);
                    }
                },
            ]
        );
        $executor->execute();
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
     * metadataKeysDataProvider
     *
     * @return array
     */
    public function metadataKeysDataProvider()
    {
        static $metadata;
        if ($metadata === null) {
            $readOnlyKeys = [
                RequestExecutor::META_REQUEST_COMPLETE => 1,
                RequestExecutor::META_CONNECTION_FINISH_TIME => 1,
                RequestExecutor::META_CONNECTION_START_TIME => 1,
                RequestExecutor::META_LAST_IO_START_TIME => 1,
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
                if ($value !== EventType::EXCEPTION) {
                    $result[] = [ $value, RequestExecutor::OPERATION_READ ];
                    $result[] = [ $value, RequestExecutor::OPERATION_WRITE ];
                }
            }
        }
        return $result;
    }

    /**
     * socketExceptionEventHandler
     *
     * @param SocketExceptionEvent $event Event object
     *
     * @return void
     */
    public function socketExceptionEventHandler(SocketExceptionEvent $event)
    {
        self::fail('Exception occurred: ' . $event->getException()->getMessage());
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
