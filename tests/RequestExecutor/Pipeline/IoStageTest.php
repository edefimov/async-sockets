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
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\Pipeline\IoStage;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
use AsyncSockets\Socket\SocketInterface;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class IoStageTest
 */
class IoStageTest extends AbstractStageTest
{
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
        $socket->expects(self::any())->method('read')->willReturn(new Frame(''));

        $metadata = $this->getMetadataStructure();
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $request->expects(self::once())
            ->method('setMetadata')
            ->with(RequestExecutorInterface::META_CONNECTION_FINISH_TIME, $testTime);

        $microTimeMock = $this->getMock('Countable', ['count']);
        $microTimeMock->expects(self::any())->method('count')->willReturn($testTime);
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable([$microTimeMock, 'count']);

        $this->eventCaller->expects(self::at(0))
            ->method('callSocketSubscribers')
            ->willReturnCallback(
                function ($mock, Event $event) use ($metadata) {
                    self::assertEquals(EventType::CONNECTED, $event->getType(), 'Incorrect event fired');
                    self::assertSame(
                        $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                        $event->getContext(),
                        'Incorrect user context'
                    );
                }
            );
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
        $socket->expects(self::any())->method('read')->willReturn(new PartialFrame(new Frame('')));

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::never())->method('callSocketSubscribers');
        $this->stage->processStage([$request]);
    }

    /**
     * testThatIncorrectOperationWontProcessed
     *
     * @return void
     */
    public function testThatIncorrectOperationWontProcessed()
    {
        $operation = $this->getMockForAbstractClass(
            'AsyncSockets\RequestExecutor\OperationInterface',
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

        $socket = $this->setupSocketForRequest($request);
        $socket->expects(self::never())->method('read')->willReturn(new PartialFrame(new Frame('')));

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::never())->method('callSocketSubscribers');
        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with wrong type must be returned');
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
        $metadata = $this->getMetadataStructure();
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);
        $request->expects(self::any())->method('getSocket')->willReturn($socket);
        $request->expects(self::once())
                ->method('setMetadata')
                ->with(RequestExecutorInterface::META_CONNECTION_FINISH_TIME, $testTime);

        $microTimeMock = $this->getMock('Countable', ['count']);
        $microTimeMock->expects(self::any())->method('count')->willReturn($testTime);
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable([$microTimeMock, 'count']);

        $exception = new NetworkSocketException($socket);
        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willThrowException($exception);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testReadInSingleRequest
     *
     * @param FrameInterface $frame Return frame
     * @param string         $eventType Event to fire
     * @param bool           $mustBeReturned Flag whether this socket must be marked as complete
     *
     * @return void
     * @dataProvider readDataProvider
     */
    public function testReadInSingleRequest(FrameInterface $frame, $eventType, $mustBeReturned)
    {
        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn($frame);
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willReturnCallback(function ($mock, Event $event) use ($frame, $eventType, $metadata) {
                                  self::assertEquals($eventType, $event->getType(), 'Incorrect event fired');
                                  if ($event instanceof ReadEvent) {
                                      self::assertSame($frame, $event->getFrame());
                                  }
                                  self::assertSame(
                                      $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                                      $event->getContext(),
                                      'Incorrect user context'
                                  );
                          });

        $result = $this->stage->processStage([$request]);
        self::assertEquals($mustBeReturned, in_array($request, $result, true), 'Incorrect return result');
    }

    /**
     * testPartialFrameReading
     *
     * @return void
     */
    public function testPartialFrameReading()
    {
        $testFrame = new PartialFrame(new Frame(md5(microtime(true))));

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn($testFrame);
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::never())
                          ->method('callSocketSubscribers');

        $result = $this->stage->processStage([$request]);
        self::assertFalse(in_array($request, $result, true), 'PartialFrame result must NOT be returned');
    }

    /**
     * testExceptionOnReading
     *
     * @return void
     */
    public function testExceptionOnReading()
    {
        $testFrame = new Frame(md5(microtime(true)));

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn($testFrame);
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $exception = new NetworkSocketException($socket);
        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willThrowException($exception);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testExceptionInSocketOnReading
     *
     * @return void
     */
    public function testExceptionInSocketOnReading()
    {
        $request   = $this->createOperationMetadata();
        $socket    = $this->setupSocketForRequest($request);
        $exception = new NetworkSocketException($socket);
        $socket->expects(self::any())->method('read')->willThrowException($exception);
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::never())->method('callSocketSubscribers');

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testThatAcceptExceptionWontFireAnyEvent
     *
     * @return void
     */
    public function testThatAcceptExceptionWontFireAnyEvent()
    {
        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willThrowException(new AcceptException($socket));
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::never())->method('callSocketSubscribers');
        $this->eventCaller->expects(self::never())->method('callExceptionSubscribers');

        $request->expects(self::once())
                ->method('setOperation')
                ->willReturnCallback(
                    function (OperationInterface $operation) {
                        self::assertInstanceOf(
                            'AsyncSockets\RequestExecutor\ReadOperation',
                            $operation,
                            'Incorrect accept operation'
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

        $result = $this->stage->processStage([$request]);
        self::assertFalse(in_array($request, $result, true), 'Exception on accept must not be returned');
    }

    /**
     * testWriteInSingleRequest
     *
     * @return void
     */
    public function testWriteInSingleRequest()
    {
        $testData = md5(microtime(true));

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::never())->method('read');
        $socket->expects(self::any())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new WriteOperation($testData));
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willReturnCallback(function ($mock, Event $event) use ($metadata) {
                                  self::assertEquals(EventType::WRITE, $event->getType(), 'Incorrect event fired');
                                  self::assertInstanceOf(
                                      'AsyncSockets\Event\WriteEvent',
                                      $event,
                                      'Unexpected event class'
                                  );
                                  self::assertSame(
                                      $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                                      $event->getContext(),
                                      'Incorrect user context'
                                  );
                          });

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Incorrect return result');
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

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $exception = new NetworkSocketException($socket);
        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willThrowException($exception);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testExceptionInSocketOnWriting
     *
     * @return void
     */
    public function testExceptionInSocketOnWriting()
    {
        $request   = $this->createOperationMetadata();
        $socket    = $this->setupSocketForRequest($request);
        $exception = new NetworkSocketException($socket);
        $socket->expects(self::never())->method('read');
        $socket->expects(self::any())->method('write')->willThrowException($exception);

        $request->expects(self::any())->method('getOperation')->willReturn(new WriteOperation('data'));
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $this->eventCaller->expects(self::once())
                          ->method('callExceptionSubscribers')
                          ->with($request, $exception);

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Operation with exception must be returned');
    }

    /**
     * testThatEmptyDataWontBeSent
     *
     * @return void
     */
    public function testThatEmptyDataWontBeSent()
    {
        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::never())->method('read');
        $socket->expects(self::never())->method('write');

        $request->expects(self::any())->method('getOperation')->willReturn(new WriteOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Incorrect return result');
    }

    /**
     * testThatEventDataWillOverwriteInitial
     *
     * @return void
     */
    public function testThatEventDataWillOverwriteInitial()
    {
        $testData = md5(microtime(true));

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::never())->method('read');
        $socket->expects(self::once())->method('write')->with($testData);

        $request->expects(self::any())->method('getOperation')->willReturn(new WriteOperation());
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willReturnCallback(
                              function ($mock, WriteEvent $event) use ($testData) {
                                  $event->getOperation()->setData($testData);
                              }
                          );

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'Incorrect return result');
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
            'read'  => 'AsyncSockets\RequestExecutor\ReadOperation',
            'write' => 'AsyncSockets\RequestExecutor\WriteOperation'
        ];

        $request = $this->createOperationMetadata();
        $socket  = $this->setupSocketForRequest($request);
        $socket->expects(self::any())->method('read')->willReturn(new Frame(''));
        $socket->expects(self::any())->method('write');

        $operation = new $map[$currentOperation]();
        $request->expects(self::any())->method('getOperation')->willReturn($operation);
        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 5;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->willReturnCallback(function ($mock, IoEvent $event) use ($metadata, $nextOperation) {
                              self::assertSame(
                                  $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                                  $event->getContext(),
                                  'Incorrect user context'
                              );

                              switch ($nextOperation) {
                                  case 'read':
                                      $event->nextIsRead();
                                      break;
                                  case 'write':
                                      $event->nextIsWrite();
                                      break;
                              }
                          });

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
    /**
     * readDataProvider
     *
     * @return array
     */
    public function readDataProvider()
    {
        return [
            [ new Frame(md5(microtime(true))), EventType::READ, true ],
            [
                new AcceptedFrame(
                    md5(microtime(true)),
                    $this->getMockForAbstractClass(
                        'AsyncSockets\Socket\SocketInterface'
                    )
                ),
                EventType::ACCEPT,
                false
            ]
        ];
    }

    /** {@inheritdoc} */
    protected function createStage()
    {
        return new IoStage($this->executor, $this->eventCaller);
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
