<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Pipeline\SslHandshakeIoHandler;
use AsyncSockets\Operation\SslHandshakeOperation;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class SslHandshakeIoHandlerTest
 */
class SslHandshakeIoHandlerTest extends AbstractOobHandlerTest
{
    /**
     * @inheritDoc
     */
    protected function createOperation()
    {
        return new SslHandshakeOperation();
    }

    /**
     * testSupportsMethod
     *
     * @return void
     */
    public function testSupportsMethod()
    {
        $operation = $this->getMockBuilder('AsyncSockets\Operation\SslHandshakeOperation')
                        ->disableOriginalConstructor()
                        ->getMock();
        self::assertTrue(
            $this->handler->supports($operation),
            'Unexpected supports result'
        );
    }

    /**
     * testExceptionWillBeThrownOnHandshakeFailure
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\SslHandshakeException
     * @expectedExceptionMessage SSL handshake failed.
     */
    public function testExceptionWillBeThrownOnHandshakeFailure()
    {
        $this->socket->expects(self::any())->method('getStreamResource')->willReturn(mt_rand(0, PHP_INT_MAX));
        $mock = $this->getMockBuilder('Countable')
                    ->setMethods(['count'])
                    ->getMockForAbstractClass();

        /** @var SslHandshakeOperation|\PHPUnit_Framework_MockObject_MockObject $operation */
        $operation = $this->getMockBuilder('AsyncSockets\Operation\SslHandshakeOperation')
                          ->disableOriginalConstructor()
                          ->setMethods(['getCipher'])
                          ->getMock();
        $operation->expects(self::any())->method('getCipher')->willReturn(mt_rand(0, PHP_INT_MAX));

        $mock->expects(self::once())->method('count')
            ->with($this->socket->getStreamResource(), true, $operation->getCipher())
            ->willReturn(false);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_enable_crypto')->setCallable([$mock, 'count']);

        $this->handler->handle(
            $this->getMockedDescriptor($operation, $this->socket, RequestDescriptor::RDS_WRITE),
            $this->executor,
            $this->mockEventHandler
        );
        self::fail('Exception wasn\'t thrown');
    }

    /**
     * testSameOperationIsReturnedIfStillInProgress
     *
     * @return void
     */
    public function testSameOperationIsReturnedIfStillInProgress()
    {
        $this->socket->expects(self::any())->method('getStreamResource')->willReturn(mt_rand(0, PHP_INT_MAX));
        $mock = $this->getMockBuilder('Countable')
                    ->setMethods(['count'])
                    ->getMockForAbstractClass();

        /** @var SslHandshakeOperation|\PHPUnit_Framework_MockObject_MockObject $operation */
        $operation = $this->getMockBuilder('AsyncSockets\Operation\SslHandshakeOperation')
                          ->disableOriginalConstructor()
                          ->getMockForAbstractClass();
        $mock->expects(self::once())->method('count')
            ->willReturn(0);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_enable_crypto')->setCallable([$mock, 'count']);

        $result = $this->handler->handle(
            $this->getMockedDescriptor($operation, $this->socket, RequestDescriptor::RDS_WRITE),
            $this->executor,
            $this->mockEventHandler
        );
        self::assertSame($operation, $result, 'Incorrect operation returned');
    }

    /**
     * testHandshakeComplete
     *
     * @return void
     */
    public function testHandshakeComplete()
    {
        $this->socket->expects(self::any())->method('getStreamResource')->willReturn(mt_rand(0, PHP_INT_MAX));
        $mock = $this->getMockBuilder('Countable')
                    ->setMethods(['count'])
                    ->getMockForAbstractClass();

        /** @var SslHandshakeOperation|\PHPUnit_Framework_MockObject_MockObject $operation */
        $operation = $this->getMockBuilder('AsyncSockets\Operation\SslHandshakeOperation')
                          ->disableOriginalConstructor()
                          ->setMethods(['getNextOperation'])
                          ->getMock();
        $nextOperation = $this->getMockBuilder('AsyncSockets\Operation\OperationInterface')
                              ->getMockForAbstractClass();

        $operation->expects(self::at(0))->method('getNextOperation')->willReturn($nextOperation);
        $mock->expects(self::once())->method('count')
            ->willReturn(true);
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_enable_crypto')->setCallable([$mock, 'count']);

        $result = $this->handler->handle(
            $this->getMockedDescriptor($operation, $this->socket, RequestDescriptor::RDS_WRITE),
            $this->executor,
            $this->mockEventHandler
        );
        self::assertSame($nextOperation, $result, 'Incorrect operation returned');
    }


    /** {@inheritdoc} */
    protected function createIoHandlerInterface()
    {
        return new SslHandshakeIoHandler();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_enable_crypto')->restoreNativeHandler();
    }
}
