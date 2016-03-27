<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class OperationMetadataTest
 */
class OperationMetadataTest extends \PHPUnit_Framework_TestCase
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
     * @var OperationMetadata
     */
    protected $operationMetadata;

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
        self::assertSame($this->socket, $this->operationMetadata->getSocket(), 'Unknown socket returned');
        self::assertSame($this->operation, $this->operationMetadata->getOperation(), 'Unknown operation returned');
        self::assertFalse($this->operationMetadata->isRunning(), 'Invalid initial running flag');
        self::assertFalse($this->operationMetadata->isPostponed(), 'Invalid initial postpone flag');
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
        $this->operationMetadata->setRunning($flag);
        self::assertEquals($flag, $this->operationMetadata->isRunning(), 'Invalid running flag');
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
            $this->operationMetadata->setMetadata($key, $value);
            $meta = $this->operationMetadata->getMetadata();
            self::assertArrayHasKey($key, $meta, 'Value does not exist');
            self::assertEquals($value, $meta[$key], 'Value is incorrect');
        } else {
            $this->operationMetadata->setMetadata($key);
            $meta = $this->operationMetadata->getMetadata();
            self::assertSame($key, $meta, 'Incorrect metadata');
        }

        $this->operationMetadata->setMetadata([]);
        self::assertGreaterThan(
            0,
            count($this->operationMetadata->getMetadata()),
            'Meta data shouldn\'t have been cleared'
        );
    }

    /**
     * testPostpone
     *
     * @param string $class Socket class
     * @param bool   $isPostponed Expected result
     *
     * @return void
     * @dataProvider socketClassDataProvider
     */
    public function testPostpone($class, $isPostponed)
    {
        $socket = $this->getMockBuilder($class)
                    ->disableOriginalConstructor()
                    ->getMockForAbstractClass();

        $object = new OperationMetadata(
            $socket,
            $this->operation,
            [],
            null
        );

        $object->postpone();
        self::assertSame($isPostponed, $object->isPostponed(), 'Incorrect postpone behaviour for ' . $class);
    }

    /**
     * testInvokeEvent
     *
     * @return void
     */
    public function testInvokeEvent()
    {
        $event   = $this->getMock('AsyncSockets\Event\Event', [], [], '', false);
        $handler = $this->getMockForAbstractClass(
            'AsyncSockets\RequestExecutor\EventHandlerInterface',
            [],
            '',
            true,
            true,
            true,
            ['invokeEvent']
        );

        $handler->expects(self::once())->method('invokeEvent')->with($event);
        $operation = new OperationMetadata($this->socket, $this->operation, [ ], $handler);

        /** @var \AsyncSockets\Event\Event $event */
        $operation->invokeEvent($event);
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
            ['AsyncSockets\Socket\AbstractSocket', false],
            ['AsyncSockets\Socket\AcceptedSocket', false],
            ['AsyncSockets\Socket\ClientSocket', false],
            ['AsyncSockets\Socket\PersistentClientSocket', true],
            ['AsyncSockets\Socket\ServerSocket', false],
            ['AsyncSockets\Socket\UdpClientSocket', false],
        ];
    }

    /**
     * boolDataProvider
     *
     * @return array
     */
    public function boolDataProvider()
    {
        return [
            [true],
            [false]
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket            = $this->getMockForAbstractClass('AsyncSockets\Socket\AbstractSocket');
        $this->operation         = $this->getMock('AsyncSockets\Operation\OperationInterface');
        $this->operationMetadata = new OperationMetadata($this->socket, $this->operation, [ ]);
    }
}
