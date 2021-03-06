<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class RequestDescriptorTest
 */
class RequestDescriptorTest extends AbstractTestCase
{
    /**
     * SocketInterface
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * Test object
     *
     * @var RequestDescriptor
     */
    protected $requestDescriptor;

    /**
     * OperationInterface
     *
     * @var OperationInterface
     */
    protected $operation;

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertSame($this->socket, $this->requestDescriptor->getSocket(), 'Unknown socket returned');
        self::assertSame($this->operation, $this->requestDescriptor->getOperation(), 'Unknown operation returned');
        self::assertFalse($this->requestDescriptor->isRunning(), 'Invalid initial running flag');
        self::assertFalse($this->requestDescriptor->isForgotten(), 'Invalid initial forget flag');
    }

    /**
     * testGetters
     *
     * @param bool $flag Flag to test
     *
     * @return void
     * @dataProvider boolDataProvider
     */
    public function testGetters($flag)
    {
        $this->requestDescriptor->setRunning($flag);
        self::assertEquals($flag, $this->requestDescriptor->isRunning(), 'Invalid running flag');
    }

    /**
     * testGetSetMetadata
     *
     * @param string|array $key Key in metadata
     * @param string|null  $value Value to set
     *
     * @return void
     * @dataProvider metadataDataProvider
     */
    public function testGetSetMetadata($key, $value)
    {
        if (!is_array($key)) {
            $this->requestDescriptor->setMetadata($key, $value);
            $meta = $this->requestDescriptor->getMetadata();
            self::assertArrayHasKey($key, $meta, 'Value does not exist');
            self::assertEquals($value, $meta[$key], 'Value is incorrect');
        } else {
            $this->requestDescriptor->setMetadata($key);
            $meta = $this->requestDescriptor->getMetadata();
            self::assertSame($key, $meta, 'Incorrect metadata');
        }

        $this->requestDescriptor->setMetadata([]);
        self::assertGreaterThan(
            0,
            count($this->requestDescriptor->getMetadata()),
            'Meta data shouldn\'t have been cleared'
        );
    }

    /**
     * testForget
     *
     * @param string $class       Socket class
     * @param bool   $isForgotten Expected result
     * @param bool   $keepAlive   Flag whether to keep this connection alive
     *
     * @return void
     * @dataProvider socketClassDataProvider
     */
    public function testForget($class, $isForgotten, $keepAlive)
    {
        $socket = $this->getMockBuilder($class)
                    ->disableOriginalConstructor()
                    ->getMockForAbstractClass();

        $object = new RequestDescriptor(
            $socket,
            $this->operation,
            [
                RequestExecutorInterface::META_KEEP_ALIVE => $keepAlive,
            ],
            null
        );

        $object->forget();
        self::assertSame($isForgotten, $object->isForgotten(), 'Incorrect forget behaviour for ' . $class);
    }

    /**
     * testInvokeEvent
     *
     * @return void
     */
    public function testInvokeEvent()
    {
        $event   = $this->getMockBuilder('AsyncSockets\Event\Event')
                            ->disableOriginalConstructor()
                            ->getMockForAbstractClass();
        $handler = $this->getMockBuilder('AsyncSockets\RequestExecutor\EventHandlerInterface')
                        ->setMethods(['invokeEvent'])
                        ->getMockForAbstractClass();

        $handler->expects(self::once())->method('invokeEvent')->with($event);
        $operation = new RequestDescriptor($this->socket, $this->operation, [ ], $handler);

        /** @var \AsyncSockets\Event\Event $event */
        $operation->invokeEvent(
            $event,
            $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                 ->getMockForAbstractClass(),
            $this->socket,
            new ExecutionContext()
        );
    }

    /**
     * testCreatingStreamContext
     *
     * @return void
     */
    public function testCreatingStreamContext()
    {
        $descriptor = new RequestDescriptor(
            $this->socket,
            $this->operation,
            [ RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT => null ]
        );

        $meta       = $descriptor->getMetadata();
        $context    = $meta[RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT];
        self::assertSame(stream_context_get_default(), $context, 'Incorrect initial context');
    }

    /**
     * testSettingStreamContext
     *
     * @return void
     */
    public function testSettingStreamContext()
    {
        $descriptor = new RequestDescriptor(
            $this->socket,
            $this->operation,
            [  ]
        );

        $descriptor->setMetadata(RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT, null);

        $meta       = $descriptor->getMetadata();
        $context    = $meta[RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT];
        self::assertSame(stream_context_get_default(), $context, 'Incorrect context set ');
    }

    /**
     * testSettingStreamContext
     *
     * @return void
     */
    public function testSettingStreamContextViaArray()
    {
        $descriptor = new RequestDescriptor(
            $this->socket,
            $this->operation,
            [  ]
        );

        $descriptor->setMetadata([ RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT => null]);

        $meta       = $descriptor->getMetadata();
        $context    = $meta[RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT];
        self::assertSame(stream_context_get_default(), $context, 'Incorrect context set ');
    }

    /**
     * metadataDataProvider
     *
     * @return array
     */
    public function metadataDataProvider()
    {
        return [
            [md5(mt_rand(1, PHP_INT_MAX)), 'value'],
            [md5(mt_rand(1, PHP_INT_MAX)), null],
            [md5(mt_rand(1, PHP_INT_MAX)), new \stdClass()],
            [md5(mt_rand(1, PHP_INT_MAX)), true],
            [md5(mt_rand(1, PHP_INT_MAX)), false],
            [md5(mt_rand(1, PHP_INT_MAX)), mt_rand(1, PHP_INT_MAX)],
            [
                [
                    md5(mt_rand(1, PHP_INT_MAX)) => 'value1',
                    md5(mt_rand(1, PHP_INT_MAX)) => null,
                    md5(mt_rand(1, PHP_INT_MAX)) => new \stdClass(),
                    md5(mt_rand(1, PHP_INT_MAX)) => true,
                    md5(mt_rand(1, PHP_INT_MAX)) => false,
                    md5(mt_rand(1, PHP_INT_MAX)) => mt_rand(1, PHP_INT_MAX),
                ],
                null
            ]
        ];
    }

    /**
     * socketClassDataProvider
     *
     * @return array
     */
    public function socketClassDataProvider()
    {
        return [
            ['AsyncSockets\Socket\AbstractSocket', true, true],
            ['AsyncSockets\Socket\AbstractSocket', false, false],
            ['AsyncSockets\Socket\AcceptedSocket', true, true],
            ['AsyncSockets\Socket\AcceptedSocket', false, false],
            ['AsyncSockets\Socket\ClientSocket', true, true],
            ['AsyncSockets\Socket\ClientSocket', false, false],
            ['AsyncSockets\Socket\PersistentClientSocket', true, true],
            ['AsyncSockets\Socket\PersistentClientSocket', false, false],
            ['AsyncSockets\Socket\ServerSocket', false, false],
            ['AsyncSockets\Socket\ServerSocket', false, true],
            ['AsyncSockets\Socket\UdpClientSocket', true, true],
            ['AsyncSockets\Socket\UdpClientSocket', false, false],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket            = $this->getMockForAbstractClass('AsyncSockets\Socket\AbstractSocket');
        $this->operation         = $this->getMockBuilder('AsyncSockets\Operation\OperationInterface')
                                        ->getMockForAbstractClass();
        $this->requestDescriptor = new RequestDescriptor($this->socket, $this->operation, [ ]);
    }
}
