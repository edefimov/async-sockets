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
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\Operation\InProgressWriteOperation;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\Pipeline\IoStage;
use AsyncSockets\RequestExecutor\Pipeline\WriteIoHandler;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class IoStageTest
 *
 * @coversDefaultClass
 */
class IoStageTest extends AbstractStageTest
{
    /**
     * Mock handler
     *
     * @var IoHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockHandler;

    /**
     * testSetConnectFinishTime
     *
     * @return void
     */
    public function testSetConnectFinishTime()
    {
        $testTime = mt_rand(1, PHP_INT_MAX);

        $request = $this->createOperationMetadata();

        $socket = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn(new Frame('', ''));

        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $request->expects(self::once())
            ->method('setMetadata')
            ->with(RequestExecutorInterface::META_CONNECTION_FINISH_TIME, $testTime);

        $microTimeMock = $this->getMock('Countable', ['count']);
        $microTimeMock->expects(self::any())->method('count')->willReturn($testTime);
        /** @var \Countable $microTimeMock */
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable([$microTimeMock, 'count']);

        $this->eventCaller->expects(self::at(0))
            ->method('callSocketSubscribers')
            ->willReturnCallback(
                function ($mock, Event $event) {
                    self::assertEquals(EventType::CONNECTED, $event->getType(), 'Incorrect event fired');
                    self::assertSame(
                        $this->metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                        $event->getContext(),
                        'Incorrect user context'
                    );
                }
            );

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $this->stage->processStage([$request]);
    }

    /**
     * testThatConnectFinishTimeIsSetOnce
     *
     * @return void
     */
    public function testThatConnectFinishTimeIsSetOnce()
    {
        $request  = $this->createOperationMetadata();
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::never())
                ->method('setMetadata')
                ->with(RequestExecutorInterface::META_CONNECTION_FINISH_TIME);

        $socket = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn(new PartialFrame(new Frame('', '')));

        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);

        $this->eventCaller->expects(self::never())->method('callSocketSubscribers');

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $this->stage->processStage([$request]);
    }

    /**
     * testThatIfNoHandlerFoundExceptionWillBeThrown
     *
     * @return void
     * @expectedException \LogicException
     */
    public function testThatIfNoHandlerFoundExceptionWillBeThrown()
    {
        $operation = $this->getMockForAbstractClass(
            'AsyncSockets\Operation\OperationInterface',
            [],
            '',
            true,
            true,
            true,
            ['getType']
        );
        $operation->expects(self::any())->method('getType')->willReturn(md5(microtime(true)));

        $request = $this->createOperationMetadata();
        $request->expects(self::any())->method('getOperation')->willReturn($operation);

        $this->setupSocketForRequest($request);

        $this->mockHandler->expects(self::any())
            ->method('supports')
            ->with($operation)
            ->willReturn(false);
        $this->stage->processStage([$request]);
        self::fail('Exception was not thrown');
    }

    /**
     * testThatIfSupportsThenHandleRequest
     *
     * @return void
     */
    public function testThatIfSupportsThenHandleRequest()
    {
        $eventCaller = $this->getMock(
            'AsyncSockets\RequestExecutor\Pipeline\EventCaller',
            [ 'setCurrentOperation', 'clearCurrentOperation' ],
            [ $this->executor ]
        );

        $operation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');

        $request = $this->createOperationMetadata();
        $request->expects(self::any())->method('getOperation')->willReturn($operation);

        $socket = $this->setupSocketForRequest($request);

        $this->mockHandler
            ->expects(self::any())
            ->method('supports')
            ->with($operation)
            ->willReturn(true);
        $this->mockHandler->expects(self::once())->method('handle')
            ->with($operation, $socket, $this->executor, $eventCaller);

        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);

        $eventCaller->expects(self::once())->method('setCurrentOperation')->with($request);
        $eventCaller->expects(self::once())->method('clearCurrentOperation');

        $stage = new IoStage($this->executor, $eventCaller, [$this->mockHandler]);
        $stage->processStage([$request]);
    }

    /**
     * testThatIfNotSupportsThenSkipRequest
     *
     * @return void
     */
    public function testThatIfNotSupportsThenSkipRequest()
    {
        $operation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');

        $request = $this->createOperationMetadata();
        $request->expects(self::any())->method('getOperation')->willReturn($operation);

        $this->setupSocketForRequest($request);

        $this->mockHandler
            ->expects(self::any())
            ->method('supports')
            ->with($operation)
            ->willReturn(false);
        $this->mockHandler->expects(self::never())->method('handle');

        try {
            $stage = new IoStage($this->executor, $this->eventCaller, [$this->mockHandler]);
            $stage->processStage([$request]);
        } catch (\LogicException $e) {
            // it's ok
        }
    }

    /**
     * testExceptionInConnectedEventHandler
     *
     * @return void
     */
    public function testExceptionInConnectedEventHandler()
    {
        $testTime = mt_rand(1, PHP_INT_MAX);
        $socket   = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $request  = $this->createOperationMetadata();
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);
        $request->expects(self::any())->method('getSocket')->willReturn($socket);
        $request->expects(self::once())
                ->method('setMetadata')
                ->with(RequestExecutorInterface::META_CONNECTION_FINISH_TIME, $testTime);

        $microTimeMock = $this->getMock('Countable', ['count']);
        $microTimeMock->expects(self::any())->method('count')->willReturn($testTime);
        /** @var \Countable $microTimeMock */
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable([$microTimeMock, 'count']);

        /** @var SocketInterface $socket */
        $exception = new NetworkSocketException($socket);
        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willThrowException($exception);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testExceptionOnReading
     *
     * @return void
     */
    public function testExceptionOnReading()
    {
        $testFrame = new Frame(md5(microtime(true)), (string) mt_rand(0, PHP_INT_MAX));

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn($testFrame);
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);

        $exception = new NetworkSocketException($socket);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $this->mockHandler->expects(self::any())->method('handle')->willThrowException($exception);
        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testExceptionOnWriting
     *
     * @return void
     */
    public function testExceptionOnWriting()
    {
        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::never())->method('read');
        $socket->expects(self::any())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new WriteOperation('data'));
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);

        $exception = new NetworkSocketException($socket);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $this->mockHandler->expects(self::any())->method('handle')->willThrowException($exception);
        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testThatPartialDataWillWrittenLater
     *
     * @return void
     */
    public function testThatPartialDataWillWrittenLater()
    {
        $testData = md5(microtime(true));
        $chunks   = str_split($testData, 4);

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::never())->method('read');
        $socket->expects(self::any())->method('write')->willReturnOnConsecutiveCalls(4, 4, 4, 4, 0);

        $request->expects(self::any())->method('getOperation')->willReturn(new WriteOperation($testData));
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willReturnCallback(function ($mock, Event $event) {
                              self::assertEquals(EventType::WRITE, $event->getType(), 'Incorrect event fired');
                              self::assertInstanceOf(
                                  'AsyncSockets\Event\WriteEvent',
                                  $event,
                                  'Unexpected event class'
                              );
                              self::assertSame(
                                  $this->metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                                  $event->getContext(),
                                  'Incorrect user context'
                              );
                          });

        $request->expects(self::once())->method('setOperation')->willReturnCallback(
            function (OperationInterface $operation) use ($chunks) {
                self::assertInstanceOf(
                    'AsyncSockets\Operation\InProgressWriteOperation',
                    $operation,
                    'Incorrect operation'
                );

                /** @var InProgressWriteOperation $operation */
                self::assertSame(
                    implode('', array_slice($chunks, 1)),
                    $operation->getData(),
                    'Incorrect data'
                );
            }
        );

        $result = $this->stage->processStage([$request]);
        self::assertFalse(in_array($request, $result, true), 'Incorrect return result');
    }

    /**
     * testRequestNotReturnedIfHandlerReturnedPassedOperation
     *
     * @return void
     */
    public function testRequestNotReturnedIfHandlerReturnedPassedOperation()
    {
        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);

        $operation = $this->getMockForAbstractClass('AsyncSockets\Operation\OperationInterface');
        $request->expects(self::any())->method('getOperation')->willReturn($operation);
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $this->mockHandler->expects(self::any())->method('handle')->willReturn($operation);
        $result = $this->stage->processStage([$request]);
        self::assertFalse(
            in_array($request, $result, true),
            'Request with returned operation passed as argument must not return'
        );
    }

    /**
     * testSettingNextOperation
     *
     * @param string $currentOperation Initial operation
     * @param string $nextOperation Next operation
     *
     * @return void
     * @dataProvider operationDataProvider
     */
    public function testSettingNextOperation($currentOperation, $nextOperation)
    {
        $map = [
            'read'  => 'AsyncSockets\Operation\ReadOperation',
            'write' => 'AsyncSockets\Operation\WriteOperation'
        ];

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn(new Frame('', ''));
        $socket->expects(self::any())->method('write');

        $operation = new $map[$currentOperation]();
        $request->expects(self::any())->method('getOperation')->willReturn($operation);
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $this->metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($this->metadata);

        $request->expects(self::once())
                ->method('setOperation')
                ->willReturnCallback(
                    function (OperationInterface $operation) use ($map, $nextOperation) {
                        self::assertInstanceOf(
                            $map[$nextOperation],
                            $operation,
                            'Incorrect target operation'
                        );
                    }
                );
        $request->expects(self::once())
                ->method('setMetadata')
                ->with(
                    [
                        RequestExecutorInterface::META_LAST_IO_START_TIME => null,
                    ]
                );

        $this->mockHandler->expects(self::any())->method('supports')->willReturn(true);
        $this->mockHandler->expects(self::any())->method('handle')->willReturn(
            $this->getMock($map[$nextOperation])
        );

        $result = $this->stage->processStage([$request]);
        self::assertFalse(in_array($request, $result, true), 'Incorrect return result');
    }

    /**
     * operationDataProvider
     *
     * @param string $targetMethod Test method
     *
     * @return array
     */
    public function operationDataProvider($targetMethod)
    {
        return $this->dataProviderFromYaml(__DIR__, __CLASS__, __FUNCTION__, $targetMethod);
    }

    /** {@inheritdoc} */
    protected function createStage()
    {
        $this->mockHandler = $this->getMockBuilder('AsyncSockets\RequestExecutor\IoHandlerInterface')
            ->setMethods(['supports', 'handle'])
            ->getMockForAbstractClass();

        return new IoStage(
            $this->executor,
            $this->eventCaller,
            [
                $this->mockHandler,
                new WriteIoHandler()
            ]
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
    }

    /**
     * createSocket
     *
     * @param \PHPUnit_Framework_MockObject_MockObject $request Request object
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|SocketInterface Created socket
     */
    private function setupSocketForRequest(\PHPUnit_Framework_MockObject_MockObject $request)
    {
        $socket = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [ ],
            '',
            true,
            true,
            true,
            [ 'read' ]
        );

        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        return $socket;
    }
}
