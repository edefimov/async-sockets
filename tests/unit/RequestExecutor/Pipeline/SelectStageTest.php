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

use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\Pipeline\SelectStage;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\SelectContext;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SelectStageTest
 */
class SelectStageTest extends AbstractStageTest
{
    /**
     * Selector
     *
     * @var AsyncSelector|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $selector;

    /**
     * testThatConnectionLessSocketsWillBeReturnedWithoutSelect
     *
     * @return void
     */
    public function testThatConnectionLessSocketsWillBeReturnedWithoutSelect()
    {
        $request = $this->createRequestDescriptor();
        $request->expects(self::any())->method('getSocket')->willReturn(
            $this->getMock('AsyncSockets\Socket\UdpClientSocket', [ ], [ ], '', false)
        );
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $this->selector->expects(self::never())->method('addSocketOperation');
        $this->selector->expects(self::never())->method('select');

        $result = $this->stage->processStage([$request]);
        self::assertTrue(in_array($request, $result, true), 'ConnectionLess socket must be returned');
    }

    /**
     * testThatSocketAddedToSelector
     *
     * @return void
     */
    public function testThatSocketAddedToSelector()
    {
        $first = $this->createRequestDescriptor();
        $first->expects(self::any())->method('getSocket')->willReturn(
            $this->getMock('AsyncSockets\Socket\SocketInterface', [ ], [ ], '', false)
        );
        $first->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $second = $this->createRequestDescriptor();
        $second->expects(self::any())->method('getSocket')->willReturn(
            $this->getMock('AsyncSockets\Socket\SocketInterface', [ ], [ ], '', false)
        );
        $second->expects(self::any())->method('getOperation')->willReturn(new WriteOperation());

        $this->selector->expects(self::exactly(2))
            ->method('addSocketOperation')
            ->withConsecutive(
                [$first, OperationInterface::OPERATION_READ],
                [$second, OperationInterface::OPERATION_WRITE]
            );

        $this->selector->expects(self::once())
            ->method('select')
            ->willReturn(new SelectContext([], []));

        $this->stage->processStage([$first, $second]);
    }

    /**
     * testEmptyArrayIsReturnedOnTimeoutException
     *
     * @return void
     */
    public function testEmptyArrayIsReturnedOnTimeoutException()
    {
        $request = $this->createRequestDescriptor();
        $request->expects(self::any())->method('getSocket')->willReturn(
            $this->getMock('AsyncSockets\Socket\SocketInterface', [ ], [ ], '', false)
        );
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $this->selector->expects(self::once())->method('select')->willThrowException(new TimeoutException());

        $result = $this->stage->processStage([$request]);
        self::assertTrue(is_array($result), 'Incorrect return value');
        self::assertEmpty($result, 'Return value must be empty');
    }

    /**
     * testNonTimeoutExceptionWontBeCaught
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\SocketException
     */
    public function testNonTimeoutExceptionWontBeCaught()
    {
        $request = $this->createRequestDescriptor();
        $request->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );
        $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $this->selector->expects(self::once())->method('select')->willThrowException(new SocketException());

        $this->stage->processStage([$request]);
        self::fail('Exception is not thrown');
    }

    /**
     * testThatLastIoOperationIsChanged
     *
     * @param SocketInterface $socket Test object
     *
     * @return void
     * @dataProvider socketTypeDataProvider
     */
    public function testThatLastIoOperationIsChanged(SocketInterface $socket)
    {
        $first = $this->createRequestDescriptor();
        $first->expects(self::any())->method('getSocket')->willReturn($socket);
        $first->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME]  = 0;
        $metadata[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] = 1;
        $first->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->selector->expects(self::any())
                       ->method('select')
                       ->willReturn(new SelectContext([], []));

        $first->expects(self::once())->method('setMetadata')->with(RequestExecutorInterface::META_LAST_IO_START_TIME);

        $this->stage->processStage([$first]);
    }

    /**
     * testTimeoutCalculation
     *
     * @param array $sockets Metadata for sockets
     * @param int   $expectedSeconds Expected calculated number of seconds
     * @param int   $expectedMicroseconds Expected calculated number of microseconds
     *
     * @return void
     * @dataProvider timeoutDataProvider
     */
    public function testTimeoutCalculation(array $sockets, $expectedSeconds, $expectedMicroseconds)
    {
        $requests = [];
        foreach ($sockets as $socket) {
            $request = $this->createRequestDescriptor();
            $request->expects(self::any())->method('getSocket')->willReturn(
                $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
            );
            $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

            $metadata = $this->getMetadataStructure();
            foreach ($socket as $metaConstName => $value) {
                $key = constant('AsyncSockets\RequestExecutor\RequestExecutorInterface::' . $metaConstName);

                $metadata[$key] = $value;
            }
            $request->expects(self::any())->method('getMetadata')->willReturn($metadata);
            $requests[] = $request;
        }

        $this->selector->expects(self::exactly(count($requests)))->method('addSocketOperation');
        $this->selector->expects(self::once())
            ->method('select')
            ->with($expectedSeconds, $expectedMicroseconds)
            ->willReturn(new SelectContext([], []));

        $this->stage->processStage($requests);
    }

    /**
     * socketTypeDataProvider
     *
     * @return array
     */
    public function socketTypeDataProvider()
    {
        return [
            [$this->getMock('AsyncSockets\Socket\SocketInterface', [ ], [ ], '', false)],
            [$this->getMock('AsyncSockets\Socket\AbstractSocket', [ ], [ ], '', false)],
            [$this->getMock('AsyncSockets\Socket\AcceptedSocket', [ ], [ ], '', false)],
            [$this->getMock('AsyncSockets\Socket\ClientSocket', [ ], [ ], '', false)],
            [$this->getMock('AsyncSockets\Socket\ServerSocket', [ ], [ ], '', false)],
            [$this->getMock('AsyncSockets\Socket\UdpClientSocket', [ ], [ ], '', false)],
        ];
    }

    /**
     * timeoutDataProvider
     *
     * @param string $targetMethod Target method name
     *
     * @return array
     */
    public function timeoutDataProvider($targetMethod)
    {
        return $this->dataProviderFromYaml(__DIR__, __CLASS__, __FUNCTION__, $targetMethod);
    }

    /** {@inheritdoc} */
    protected function createStage()
    {
        return new SelectStage($this->executor, $this->eventCaller, $this->selector);
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->selector = $this->getMock('AsyncSockets\Socket\AsyncSelector', ['select', 'addSocketOperation']);
        parent::setUp();
    }
}
