<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\RequestExecutor\Pipeline\DisconnectStage;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class DisconnectStageTest
 */
class DisconnectStageTest extends AbstractStageTest
{
    /**
     * Selector
     *
     * @var AsyncSelector|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $selector;

    /**
     * testCantDisconnectTwice
     *
     * @return void
     */
    public function testCantDisconnectTwice()
    {
        $request = $this->createRequestDescriptor();
        $socket  = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            false,
            false,
            true,
            ['close']
        );

        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_REQUEST_COMPLETE] = true;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $request->expects(self::never())
            ->method('setMetadata')
            ->with(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
        $socket->expects(self::never())->method('close');

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Operation must be returned as result');
    }

    /**
     * testDisconnectConnectedSocket
     *
     * @param string     $socketClass Socket class to test
     * @param callable[] $phpEnv Array php function name => callable for this test
     *
     * @return void
     * @dataProvider socketClassDataProvider
     */
    public function testDisconnectConnectedSocket($socketClass, array $phpEnv = [])
    {
        $request = $this->createRequestDescriptor();
        $socket  = $this->getMockForAbstractClass(
            $socketClass,
            [],
            '',
            false,
            false,
            true,
            ['close']
        );

        foreach ($phpEnv as $functionName => $callable) {
            PhpFunctionMocker::getPhpFunctionMocker($functionName)->setCallable($callable);
        }

        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_REQUEST_COMPLETE]       = false;
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 10;
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME]  = 0;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $request->expects(self::once())->method('setMetadata')
            ->with(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
        $socket->expects(self::once())->method('close');
        $this->eventCaller->expects(self::at(0))
            ->method('callSocketSubscribers')
            ->willReturnCallback(
                function ($mock, Event $event) use ($metadata) {
                    self::assertEquals(EventType::DISCONNECTED, $event->getType(), 'Wrong disconnect event');
                    self::assertSame(
                        $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                        $event->getContext(),
                        'Incorrect context'
                    );
                }
            );

        $this->eventCaller->expects(self::at(1))
             ->method('callSocketSubscribers')
             ->willReturnCallback(
                 function ($mock, Event $event) use ($metadata) {
                     self::assertEquals(EventType::FINALIZE, $event->getType(), 'Wrong final event');
                     self::assertSame(
                         $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                         $event->getContext(),
                         'Incorrect context'
                     );
                 }
             );

        $this->selector->expects(self::once())->method('removeAllSocketOperations')->with($request);

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Operation must be returned as result');
    }

    /**
     * testExceptionOnDisconnectConnectedSocket
     *
     * @return void
     */
    public function testExceptionOnDisconnectConnectedSocket()
    {
        PhpFunctionMocker::getPhpFunctionMocker('feof')->setCallable(
            function () {
                return false;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
            function () {
                return '127.0.0.1:1234';
            }
        );
        $request = $this->createRequestDescriptor();
        $socket  = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [],
            '',
            false,
            false,
            true,
            ['close']
        );

        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_REQUEST_COMPLETE]       = false;
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 10;
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME]  = 0;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $request->expects(self::once())->method('setMetadata')
            ->with(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
        $socket->expects(self::once())->method('close')->willThrowException(new NetworkSocketException($socket));

        $this->eventCaller
            ->expects(self::once())
            ->method('callExceptionSubscribers')
            ->id('exception_call');

        $this->eventCaller->expects(self::once())
             ->method('callSocketSubscribers')
             ->after('exception_call')
             ->willReturnCallback(function ($mock, Event $event) use ($metadata) {
                 self::assertEquals(EventType::FINALIZE, $event->getType(), 'Wrong final event');
                 self::assertSame(
                     $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                     $event->getContext(),
                     'Incorrect context'
                 );
             });

        $this->selector->expects(self::once())->method('removeAllSocketOperations')->with($request);

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Operation must be returned as result');
    }

    /**
     * testConnectedSocketWillNotBeDisconnectedIfStillConnected
     *
     * @return void
     */
    public function testConnectedSocketWillNotBeDisconnectedIfStillConnected()
    {
        $request = $this->createRequestDescriptor();
        $socket  = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\ClientSocket',
            [],
            '',
            false,
            false,
            true,
            ['close']
        );

        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata                                                        = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_REQUEST_COMPLETE]       = false;
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 10;
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME]  = 0;
        $metadata[RequestExecutorInterface::META_KEEP_ALIVE]             = true;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $request->expects(self::never())->method('setMetadata');
        $socket->expects(self::never())->method('close');

        $this->eventCaller->expects(self::never())->method('callSocketSubscribers');

        $this->selector->expects(self::never())->method('removeAllSocketOperations')->with($request);

        PhpFunctionMocker::getPhpFunctionMocker('feof')->setCallable(function () {
            return false;
        });
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(function () {
            return '127.0.0.1:59678';
        });

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Operation must not be returned as result');
    }

    /**
     * testThatForgottenSocketsMarkedAsCompleted
     *
     * @return void
     */
    public function testThatForgottenSocketsMarkedAsCompleted()
    {
        $request = $this->createRequestDescriptor();
        $socket  = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\ClientSocket',
            [],
            '',
            false,
            false,
            true,
            ['close']
        );

        $request->expects(self::any())->method('getSocket')->willReturn($socket);

        $metadata                                                        = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_REQUEST_COMPLETE]       = false;
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 10;
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME]  = 0;
        $metadata[RequestExecutorInterface::META_KEEP_ALIVE]             = true;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);
        $request->expects(self::any())->method('isForgotten')->willReturn(true);

        $socket->expects(self::never())->method('close');

        $this->eventCaller->expects(self::once())->method('callSocketSubscribers');

        $this->selector->expects(self::once())->method('removeAllSocketOperations')->with($request);

        PhpFunctionMocker::getPhpFunctionMocker('feof')->setCallable(function () {
            return false;
        });
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(function () {
            return '127.0.0.1:59678';
        });

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Operation must not be returned as result');
    }

    /**
     * socketClassDataProvider
     *
     * @return array
     */
    public function socketClassDataProvider()
    {
        return [
            [
                'AsyncSockets\Socket\SocketInterface',
                [
                    'feof' => function () {
                        return false;
                    },
                    'stream_socket_get_name' => function () {
                        return '127.0.0.1:5436';
                    },
                ],
            ],
            [
                'AsyncSockets\Socket\PersistentClientSocket',
                [
                    'feof' => function () {
                        return true;
                    },
                    'stream_socket_get_name' => function () {
                        return '127.0.0.1:5436';
                    }
                ]
            ],
            [
                'AsyncSockets\Socket\PersistentClientSocket',
                [
                    'feof' => function () {
                        return false;
                    },
                    'stream_socket_get_name' => function () {
                        return false;
                    }
                ]
            ]
        ];
    }

    /** {@inheritdoc} */
    protected function createStage()
    {
        return new DisconnectStage($this->executor, $this->eventCaller, $this->executionContext, $this->selector);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->selector = $this->getMockBuilder('AsyncSockets\Socket\AsyncSelector')
                                ->setMethods(['removeAllSocketOperations'])
                                ->getMock();

        parent::setUp();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('feof')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
    }
}
