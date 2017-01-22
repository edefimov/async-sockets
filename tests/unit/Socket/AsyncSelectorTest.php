<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class AsyncSelectorTest
 *
 * @SuppressWarnings("unused")
 * @SuppressWarnings("TooManyMethods")
 */
class AsyncSelectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test socket
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * Socket resource for test
     *
     * @var resource
     */
    private $socketResource;

    /**
     * AsyncSelector
     *
     * @var AsyncSelector
     */
    private $selector;

    /**
     * Test that socket object will be returned in read context property
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testSelectReadWrite($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperation($this->socket, $operation);
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * Check validity of select operation
     *
     * @param int $countRead Amount of sockets, that must be ready to read
     * @param int $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     */
    private function verifySocketSelectOperation($countRead, $countWrite)
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return '';
            }
        );
        $result = $this->selector->select(0);
        self::assertCount($countRead, $result->getRead(), 'Unexpected result of read selector');
        self::assertCount($countWrite, $result->getWrite(), 'Unexpected result of write selector');
        $testSocket = $result->getRead() + $result->getWrite();
        self::assertSame($this->socket, $testSocket[ 0 ], 'Unexpected object returned for operation');
    }

    /**
     * testAddSocketArrayReadWrite
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider socketOperationDataProvider
     */
    public function testAddSocketArrayReadWrite($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperationArray([ $this->socket ], $operation);
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * testAddSocketArrayWrite
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @depends      testAddSocketArrayReadWrite
     * @dataProvider socketOperationDataProvider
     */
    public function testAddSocketArrayReadWriteComplexArray($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperationArray(
            [
                [ $this->socket, $operation ],
            ]
        );
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * testExceptionOnEmptySocketWhenSelect
     *
     * @return void
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnEmptySocketWhenSelect()
    {
        $this->selector->select(0);
    }

    /**
     * testAddSocketArrayWithInvalidArrayStructure
     *
     * @param array $socketData Socket add getData
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider invalidSocketAddDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testAddSocketArrayWithInvalidArrayStructure(array $socketData)
    {
        $this->selector->addSocketOperationArray($socketData);
    }

    /**
     * testRemoveSocket
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider socketOperationDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveSocket($operation)
    {
        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->removeSocketOperation($this->socket, $operation);
        $this->selector->select(0);
    }

    /**
     * testStreamSelectFail
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \AsyncSockets\Exception\SocketException
     */
    public function testStreamSelectFail($operation)
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(
            function () {
                return false;
            }
        );

        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->select(0);
    }

    /**
     * testTimeOutExceptionWillBeThrown
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \AsyncSockets\Exception\TimeoutException
     */
    public function testTimeOutExceptionWillBeThrown($operation)
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(
            function (array &$read = null, array &$write = null, array &$oob = null) {
                $read  = [ ];
                $write = [ ];
                $oob   = [ ];

                return 0;
            }
        );

        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->select(0);
    }

    /**
     * testRemoveAllSocketOperations
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider socketOperationDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveAllSocketOperations($operation)
    {
        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->removeAllSocketOperations($this->socket);
        $this->selector->select(0);
    }

    /**
     * testChangeSocketOperation
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @depends      testRemoveAllSocketOperations
     * @dataProvider socketOperationDataProvider
     */
    public function testChangeSocketOperation($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperationArray(
            [
                [ $this->socket, OperationInterface::OPERATION_READ ],
                [ $this->socket, OperationInterface::OPERATION_WRITE ],
            ]
        );

        $this->selector->changeSocketOperation($this->socket, $operation);
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * testThatIfSelectFailedIncompleteSleepWillBeCalled
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\TimeoutException
     */
    public function testThatIfSelectFailedIncompleteSleepWillBeCalled()
    {
        $usleep = $this->getMockBuilder('Countable')->setMethods([ 'count' ])->getMockForAbstractClass();
        $usleep->expects(self::exactly(AsyncSelector::ATTEMPT_COUNT_FOR_INFINITE_TIMEOUT - 1))
            ->method('count')
            ->with(AsyncSelector::ATTEMPT_DELAY);

        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return 1;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return false;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('usleep')->setCallable([ $usleep, 'count' ]);

        $this->selector->addSocketOperation($this->socket, OperationInterface::OPERATION_READ);
        $this->selector->select(0, 2 * AsyncSelector::ATTEMPT_DELAY);
    }

    /**
     * testReadForIoWithServerSocket
     *
     * @return void
     */
    public function testReadForIoWithServerSocket()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return false;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return $this->socket->getStreamResource();
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
            function ($resource, $wantPeer) {
                if ($resource !== $this->socket->getStreamResource()) {
                    return \stream_socket_get_name($resource, $wantPeer);
                }

                return $wantPeer ? null : '127.0.0.1:35424';
            }
        );

        $this->selector->addSocketOperation($this->socket, OperationInterface::OPERATION_READ);
        $context = $this->selector->select(null);
        self::assertCount(1, $context->getRead(), 'Server socket was not returned');
    }

    /**
     * testIoOperations
     *
     * @param \Closure   $createMockFn SocketInterface fn(); Socket create function
     * @param callable[] $callables Initial callables
     * @param string     $operation Operation name
     * @param \Closure   $expectedResultFn Array of expected results in context form array fn(SocketInterface $socket)
     * @param bool       $shouldReturnSocket Flag if socket must be returned in select context
     *
     * @dataProvider socketOperationsDataProvider
     */
    public function testIoOperations(
        \Closure $createMockFn,
        array $callables,
        $operation,
        \Closure $expectedResultFn,
        $shouldReturnSocket
    ) {
        $socket = $createMockFn();
        if (!$shouldReturnSocket) {
            $this->setExpectedException('AsyncSockets\Exception\TimeoutException');
        }

        foreach ($callables as $name => $callable) {
            PhpFunctionMocker::getPhpFunctionMocker($name)->setCallable($callable);
        }

        $this->selector->addSocketOperation($socket, $operation);
        $context = $this->selector->select(0);

        $expectedResult = $expectedResultFn($socket);
        self::assertSame(count($context->getRead()), count($expectedResult['read']), 'Incorrect data in read array');
        self::assertSame(count($context->getWrite()), count($expectedResult['write']), 'Incorrect data in write array');
        self::assertSame(count($context->getOob()), count($expectedResult['oob']), 'Incorrect data in oob array');

        self::assertContains(
            $socket,
            array_merge($context->getRead(), $context->getWrite(), $context->getOob()),
            'Socket was not found in result context'
        );
    }

    /**
     * socketOperationDataProvider
     *
     * @return array
     */
    public function socketOperationDataProvider()
    {
        // form: operation, ready to read, ready to write
        return [
            [ OperationInterface::OPERATION_READ, 1, 0 ],
            [ OperationInterface::OPERATION_WRITE, 0, 1 ],
        ];
    }

    /**
     * invalidSocketAddDataProvider
     *
     * @return array
     */
    public function invalidSocketAddDataProvider()
    {
        return [
            [ [ [ $this->socket ] ] ],
            [ [ $this->socket ] ],
        ];
    }

    /**
     * socketOperationsDataProvider
     *
     * @return array
     */
    public function socketOperationsDataProvider()
    {
        $resource = fopen('php://temp', 'r+');
        $fnCreateMock = function ($className) use ($resource) {
            return function () use ($className, $resource) {
                $mock = $this->getMockBuilder($className)
                             ->setMethods(['getStreamResource'])
                             ->disableOriginalConstructor()
                             ->getMockForAbstractClass();
                $mock->expects(self::any())->method('getStreamResource')->willReturn($resource);

                return $mock;
            };
        };

        $clientFn = $fnCreateMock('AsyncSockets\Socket\ClientSocket');
        $serverFn = $fnCreateMock('AsyncSockets\Socket\ServerSocket');
        $verifyFn = function ($keyName) {
            return function (SocketInterface $socket) use ($keyName) {
                return ($keyName ? [ $keyName => [$socket] ] : []) + [
                    'read'  => [],
                    'write' => [],
                    'oob'   => [],
                ];
            };
        };

        $streamSocketRecvFromOobDataReady = function ($socket, $length, $flags) {
            return $flags & (STREAM_PEEK | STREAM_OOB) === (STREAM_PEEK | STREAM_OOB) ? true : false;
        };

        return [
            // 0. client read usual
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [ $resource ];
                        $write = [ ];
                        $oob = [ ];

                        return 1;
                    },
                    'stream_socket_recvfrom' => function () {
                        return true;
                    }
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn('read'),
                true
            ],
            // 1. client read not ready
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [ ];
                        $oob = [ ];

                        return 0;
                    },
                    'stream_socket_recvfrom' => function () {
                        return false;
                    }
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn(null),
                false
            ],
            // 2. client write
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [ $resource ];
                        $oob = [ ];

                        return 1;
                    },
                ],
                OperationInterface::OPERATION_WRITE,
                $verifyFn('write'),
                true
            ],
            // 3. client write not read
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [ ];
                        $oob = [ ];

                        return 0;
                    },
                ],
                OperationInterface::OPERATION_WRITE,
                $verifyFn(null),
                false
            ],
            // 4. client oob with read
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [  ];
                        $oob = [ $resource ];

                        return 1;
                    },
                    'stream_socket_recvfrom' => $streamSocketRecvFromOobDataReady
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn('oob'),
                true
            ],
            // 5. client oob with read when oob not ready
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [  ];
                        $oob = [ $resource ];

                        return 1;
                    },
                    'stream_socket_recvfrom' => function () {
                        return false;
                    }
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn('oob'),
                false
            ],
            // 6. client oob with write
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [  ];
                        $oob = [ $resource ];

                        return 1;
                    },
                    'stream_socket_recvfrom' => $streamSocketRecvFromOobDataReady
                ],
                OperationInterface::OPERATION_WRITE,
                $verifyFn('oob'),
                true
            ],
            // 7. server read
            [
                $serverFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [ $resource ];
                        $write = [ ];
                        $oob = [ ];

                        return 1;
                    },
                    'stream_socket_get_name' => function ($socket, $isPeer) {
                        return $isPeer ? false : '127.0.0.1:867';
                    }
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn('read'),
                true
            ],
            // 8. server write
            [
                $serverFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read = [  ];
                        $write = [ $resource ];
                        $oob = [ ];

                        return 1;
                    },
                ],
                OperationInterface::OPERATION_WRITE,
                $verifyFn('write'),
                true
            ],
            // 9. server oob with read
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read  = [  ];
                        $write = [  ];
                        $oob   = [ $resource ];

                        return 1;
                    },
                    'stream_socket_get_name' => function ($socket, $isPeer) {
                        return $isPeer ? false : '127.0.0.1:867';
                    },
                    'stream_socket_recvfrom' => $streamSocketRecvFromOobDataReady,
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn('oob'),
                true
            ],
            // 10. server oob with read not ready
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read  = [  ];
                        $write = [  ];
                        $oob   = [ $resource ];

                        return 1;
                    },
                    'stream_socket_get_name' => function ($socket, $isPeer) {
                        return $isPeer ? false : '127.0.0.1:867';
                    },
                    'stream_socket_recvfrom' => function () {
                        return false;
                    },
                ],
                OperationInterface::OPERATION_READ,
                $verifyFn(null),
                false
            ],
            // 11. server oob with write
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read  = [  ];
                        $write = [  ];
                        $oob   = [ $resource ];

                        return 1;
                    },
                    'stream_socket_recvfrom' => $streamSocketRecvFromOobDataReady
                ],
                OperationInterface::OPERATION_WRITE,
                $verifyFn('oob'),
                true
            ],
            // 12. server oob not ready with write
            [
                $clientFn,
                [
                    'stream_select' => function (
                        array &$read = null,
                        array &$write = null,
                        array &$oob = null
                    ) use (
                        $resource
                    ) {
                        $read  = [  ];
                        $write = [  ];
                        $oob   = [ $resource ];

                        return 1;
                    },
                    'stream_socket_recvfrom' => function () {
                        return false;
                    }
                ],
                OperationInterface::OPERATION_WRITE,
                $verifyFn('null'),
                false
            ],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socketResource = fopen('php://temp', 'r+');
        $this->socket         = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [ ],
            '',
            false,
            true,
            true,
            [ 'getStreamResource' ]
        );
        $this->socket->expects(self::any())->method('getStreamResource')->willReturn($this->socketResource);

        $this->selector = new AsyncSelector();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('usleep')->restoreNativeHandler();
        $this->socket->close();
    }
}
