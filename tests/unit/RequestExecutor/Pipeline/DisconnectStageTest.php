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
use AsyncSockets\RequestExecutor\Pipeline\DisconnectStage;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

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
        $request = $this->createOperationMetadata();
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
     * @return void
     */
    public function testDisconnectConnectedSocket()
    {
        $request = $this->createOperationMetadata();
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
        $request = $this->createOperationMetadata();
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

    /** {@inheritdoc} */
    protected function createStage()
    {
        return new DisconnectStage($this->executor, $this->eventCaller, $this->selector);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->selector = $this->getMock('AsyncSockets\Socket\AsyncSelector', ['removeAllSocketOperations']);
        parent::setUp();
    }
}
