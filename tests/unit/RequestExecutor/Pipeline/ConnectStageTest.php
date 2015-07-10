<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Pipeline\ConnectStage;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class ConnectStageTest
 */
class ConnectStageTest extends AbstractStageTest
{
    /**
     * LimitationSolverInterface
     *
     * @var LimitationSolverInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $solver;

    /**
     * testProcessScheduledDecision
     *
     * @return void
     */
    public function testProcessScheduledDecision()
    {
        $microtimeStubResult = time();


        $this->solver->expects(self::any())->method('decide')->willReturnOnConsecutiveCalls(
            LimitationSolverInterface::DECISION_OK,
            LimitationSolverInterface::DECISION_PROCESS_SCHEDULED
        );

        $testMetadata                                                         = $this->getMetadataStructure();
        $testMetadata[ RequestExecutorInterface::META_ADDRESS ]               = md5(microtime());
        $testMetadata[ RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT ] = stream_context_get_default();

        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            true,
            true,
            true,
            ['open', 'setBlocking']
        );
        $socket->expects(self::once())->method('open')->with(
            $testMetadata[RequestExecutorInterface::META_ADDRESS],
            $testMetadata[RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT]
        );
        $socket->expects(self::once())->method('setBlocking')->with(false);

        $first  = $this->createOperationMetadata();
        $second = $this->createOperationMetadata();

        $isRunning = false;
        $first->expects(self::once())->method('initialize');
        $first->expects(self::any())->method('getSocket')->willReturn($socket);
        $first->expects(self::any())->method('getMetadata')->willReturn($testMetadata);
        $first->expects(self::once())
            ->method('setRunning')
            ->with(true)
            ->willReturnCallback(
                function () use (&$isRunning) {
                    $isRunning = true;
                }
            );
        $first->expects(self::any())->method('isRunning')->willReturnCallback(function () use (&$isRunning) {
            return $isRunning;
        });
        $first->expects(self::any())->method('setMetadata')->with(
            RequestExecutorInterface::META_CONNECTION_START_TIME,
            $microtimeStubResult
        );

        $this->eventCaller->expects(self::once())
            ->method('callSocketSubscribers')
            ->willReturnCallback(function ($mock, Event $event) {
                /** @var OperationMetadata|\PHPUnit_Framework_MockObject_MockObject $mock */
                self::assertSame($mock->getSocket(), $event->getSocket(), 'Incorrect socket passed');
                self::assertEquals(EventType::INITIALIZE, $event->getType(), 'Wrong event fired on connect stage');
            });
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable(
            function () use ($microtimeStubResult) {
                return $microtimeStubResult;
            }
        );

        $second->expects(self::never())->method('initialize');
        $second->expects(self::never())->method('setRunning');
        $second->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );

        $connected = $this->stage->processStage([ $first,  $second, ]);
        self::assertTrue(
            in_array($first, $connected, true),
            'First operation must be returned as connected'
        );
        self::assertFalse(
            in_array($second, $connected, true),
            'Second operation must NOT be returned as connected'
        );
    }

    /**
     * testExceptionWillBeThrownOnInvalidDecision
     *
     * @return void
     * @expectedException \LogicException
     */
    public function testExceptionWillBeThrownOnInvalidDecision()
    {
        $this->solver->expects(self::any())->method('decide')->willReturn(md5(microtime(true)));
        $first = $this->createOperationMetadata();

        $first->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );

        $this->stage->processStage([ $first, ]);
    }

    /**
     * testThatAlreadyRunningObjectWillBeSkipped
     *
     * @return void
     */
    public function testThatAlreadyRunningObjectWillBeSkipped()
    {
        $this->solver->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);
        $first = $this->createOperationMetadata();

        $first->expects(self::any())->method('isRunning')->willReturn(true);
        $first->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );
        $first->expects(self::never())->method('initialize');
        $first->expects(self::never())->method('setRunning');


        $connected = $this->stage->processStage([ $first, ]);
        self::assertTrue(
            in_array($first, $connected, true),
            'Running operation must be returned as connected'
        );
    }

    /**
     * testThatConnectedSocketWillBeSkipped
     *
     * @return void
     */
    public function testThatConnectedSocketWillBeSkipped()
    {
        $this->solver->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);
        $first = $this->createOperationMetadata();

        $first->expects(self::any())->method('isRunning')->willReturnOnConsecutiveCalls(false, true);
        $first->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );
        $first->expects(self::never())->method('initialize');
        $first->expects(self::never())->method('setRunning');

        $testMetadata = $this->getMetadataStructure();
        $testMetadata[RequestExecutorInterface::META_CONNECTION_START_TIME] = mt_rand(1, PHP_INT_MAX);

        $first->expects(self::any())->method('getMetadata')->willReturn($testMetadata);

        $connected = $this->stage->processStage([ $first, ]);
        self::assertTrue(
            in_array($first, $connected, true),
            'Running operation must be returned as connected'
        );
    }

    /**
     * testNetworkExceptionDuringConnectWillBeHandled
     *
     * @return void
     */
    public function testNetworkExceptionDuringConnectWillBeHandled()
    {
        /** @var SocketInterface|\PHPUnit_Framework_MockObject_MockObject $socket */
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            true,
            true,
            true,
            ['setBlocking']
        );

        $socket->expects(self::once())->method('setBlocking')->willThrowException(
            new NetworkSocketException($socket)
        );

        $this->solver->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);
        $first  = $this->createOperationMetadata();

        $first->expects(self::once())->method('initialize');
        $first->expects(self::any())->method('getSocket')->willReturn($socket);
        $first->expects(self::any())->method('getMetadata')->willReturn(
            $this->getMetadataStructure()
        );

        $first->expects(self::any())->method('isRunning')->willReturn(false);

        $first->expects(self::any())
            ->method('setMetadata')
            ->withConsecutive(
                [RequestExecutorInterface::META_CONNECTION_START_TIME],
                [RequestExecutorInterface::META_REQUEST_COMPLETE, true]
            );
        $this->setupEventCallerForSocketException($this->eventCaller);

        $connected = $this->stage->processStage([ $first, ]);
        self::assertFalse(
            in_array($first, $connected, true),
            'Operation with exception must not be returned as connected'
        );
    }

    /**
     * testPassStreamContextAsArray
     *
     * @param array $context Arguments for stream context
     * @param array $expected Excpected values in php function call
     *
     * @return void
     * @dataProvider streamContextDataProvider
     */
    public function testPassStreamContextAsArray(array $context, array $expected)
    {
        $defaultContext = stream_context_get_default();
        $testMetadata   = $this->getMetadataStructure();
        $testMetadata[RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT] = $context;

        $this->solver->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);
        $first  = $this->createOperationMetadata();
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            true,
            true,
            true,
            ['open']
        );

        $socket->expects(self::once())->method('open')->with(null, $defaultContext);

        $first->expects(self::once())->method('initialize');
        $first->expects(self::any())->method('getSocket')->willReturn($socket);
        $first->expects(self::any())->method('getMetadata')->willReturn($testMetadata);
        $first->expects(self::any())->method('isRunning')->willReturnOnConsecutiveCalls(false, true);

        PhpFunctionMocker::getPhpFunctionMocker('stream_context_create')->setCallable(
            function ($options, $params) use ($expected, $defaultContext) {
                self::assertSame($expected['options'], $options, 'Invalid options passed to stream_context_create');
                self::assertSame($expected['params'], $params, 'Invalid params passed to stream_context_create');
                return $defaultContext;
            }
        );

        $this->stage->processStage([ $first, ]);
    }

    /**
     * testNullWillReturnedIfStreamContextInvalid
     *
     * @return void
     */
    public function testNullWillReturnedIfStreamContextInvalid()
    {
        $testMetadata                                                         = $this->getMetadataStructure();
        $testMetadata[ RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT ] = md5(microtime(true));

        $this->solver->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);

        $first  = $this->createOperationMetadata();
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            true,
            true,
            true,
            ['open']
        );
        $socket->expects(self::once())->method('open')->with(null, null);

        $first->expects(self::once())->method('initialize');
        $first->expects(self::any())->method('getSocket')->willReturn($socket);
        $first->expects(self::any())->method('getMetadata')->willReturn($testMetadata);

        $first->expects(self::any())->method('isRunning')->willReturnOnConsecutiveCalls(false, true);
        $this->stage->processStage([ $first, ]);
    }

    /**
     * streamContextDataProvider
     *
     * @return array
     */
    public function streamContextDataProvider()
    {
        // passing params, expected params
        return [
            [
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
            ],

            [
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                ],
                [
                    'options' => [
                        'socket' => [
                            'bindto' => '127.0.0.1:8087'
                        ]
                    ],
                    'params' => [ ]
                ],
            ],

            [
                [
                    'params' => [
                        'value' => 'test'
                    ]
                ],
                [
                    'options' => [ ],
                    'params' => [
                        'value' => 'test'
                    ]
                ],
            ],

            [
                [],
                [
                    'options' => [ ],
                    'params' => [ ]
                ],
            ],
        ];
    }

    /** {@inheritdoc} */
    protected function createStage()
    {
        return new ConnectStage($this->executor, $this->eventCaller, $this->solver);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->solver = $this->getMockForAbstractClass(
            'AsyncSockets\RequestExecutor\LimitationSolverInterface',
            [ ],
            '',
            true,
            true,
            true,
            [ 'decide' ]
        );
        parent::setUp();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_context_create')->restoreNativeHandler();
    }
}
