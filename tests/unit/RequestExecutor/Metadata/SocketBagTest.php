<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SocketBagTest
 */
class SocketBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Socket
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * Bag
     *
     * @var SocketBag
     */
    private $bag;

    /**
     * Connect timeout
     *
     * @var double
     */
    private $connectTimeout;

    /**
     * I/O timeout
     *
     * @var double
     */
    private $ioTimeout;

    /**
     * RequestExecutorInterface
     *
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $executor;

    /**
     * Mocked operation
     *
     * @var OperationInterface
     */
    private $operation;

    /**
     * testAddSocket
     *
     * @return void
     */
    public function testAddSocket()
    {
        self::assertCount(0, $this->bag, 'Incorrect initial count');
        $this->bag->addSocket(
            $this->socket,
            $this->operation,
            [ ],
            $this->getMock('AsyncSockets\RequestExecutor\EventHandlerInterface')
        );
        self::assertCount(1, $this->bag, 'Count haven\'t changed');

        $initialState = [
            RequestExecutorInterface::META_CONNECTION_TIMEOUT => [
                'value' => $this->connectTimeout,
                'message' => 'Incorrect initial connect timeout'
            ],
            RequestExecutorInterface::META_IO_TIMEOUT => [
                'value' => $this->ioTimeout,
                'message' => 'Incorrect initial I/O timeout'
            ],
            RequestExecutorInterface::META_BYTES_SENT => [
                'value' => 0,
                'message' => 'Incorrect bytes sent counter initial value'
            ],
            RequestExecutorInterface::META_BYTES_RECEIVED => [
                'value' => 0,
                'message' => 'Incorrect bytes received counter initial value'
            ],
            RequestExecutorInterface::META_RECEIVE_SPEED => [
                'value' => 0,
                'message' => 'Incorrect bytes receive speed counter initial value'
            ],
        ];

        $meta = $this->bag->getSocketMetaData($this->socket);
        foreach ($initialState as $key => $state) {
            self::assertSame($state['value'], $meta[$key], $state['message']);
        }
    }

    /**
     * testHasSocket
     *
     * @return void
     */
    public function testHasSocket()
    {
        $this->bag->addSocket($this->socket, $this->operation, [  ]);
        self::assertTrue(
            $this->bag->hasSocket($this->socket),
            'hasSocket returned false for added socket'
        );

        self::assertFalse(
            $this->bag->hasSocket(clone $this->socket),
            'hasSocket returned true for not added socket'
        );
    }

    /**
     * testMetadataIsFilled
     *
     * @return void
     * @depends testAddSocket
     */
    public function testMetadataIsFilled()
    {
        $this->bag->addSocket($this->socket, $this->operation, [ ]);
        $meta = $this->bag->getSocketMetaData($this->socket);
        $ref  = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutorInterface');
        foreach ($ref->getConstants() as $name => $value) {
            if (!preg_match('#META_.*?#', $name)) {
                continue;
            }

            self::assertArrayHasKey($value, $meta, "Metadata key {$name} is not defined in getSocketMetaData results");
        }
    }

    /**
     * testRemoveSocket
     *
     * @return void
     * @depends testHasSocket
     * @depends testMetadataIsFilled
     */
    public function testRemoveSocket()
    {
        $this->bag->addSocket($this->socket, $this->operation, [  ]);
        $this->bag->removeSocket($this->socket);
        self::assertFalse(
            $this->bag->hasSocket($this->socket),
            'hasSocket returned true for removed socket'
        );
    }

    /**
     * testNonAddedSocketRemove
     *
     * @return void
     * @depends testRemoveSocket
     */
    public function testNonAddedSocketRemove()
    {
        $this->bag->removeSocket($this->socket);
        $this->bag->postponeSocket($this->socket);
        self::assertFalse(
            $this->bag->hasSocket($this->socket),
            'hasSocket returned true for removed socket'
        );
    }
    
    /**
     * testCantAddSameSocketTwice
     *
     * @return void
     * @depends testAddSocket
     * @expectedException \LogicException
     */
    public function testCantAddSameSocketTwice()
    {
        $this->bag->addSocket(
            $this->socket,
            $this->operation,
            [  ]
        );

        $this->bag->addSocket(
            $this->socket,
            $this->operation,
            [  ]
        );
    }

    /**
     * testCantRemoveSocketDuringExecute
     *
     * @return void
     * @expectedException \LogicException
     */
    public function testCantRemoveSocketDuringExecute()
    {
        $this->bag->addSocket($this->socket, $this->operation, []);
        $this->executor->expects(self::once())->method('isExecuting')->willReturn(true);
        $this->bag->removeSocket($this->socket);
    }

    /**
     * testMetadataCanChange
     *
     * @param string $phpName Name in php file
     * @param string $key Key in metadata array
     * @param bool   $isReadOnly Flag whether it is read only constant
     *
     * @return void
     * @dataProvider metadataKeysDataProvider
     */
    public function testMetadataCanChange($phpName, $key, $isReadOnly)
    {
        $this->bag->addSocket(
            $this->socket,
            $this->operation,
            [  ]
        );
        $originalMeta = $this->bag->getSocketMetaData($this->socket);

        $this->bag->setSocketMetaData($this->socket, $key, mt_rand(1, PHP_INT_MAX));
        $newMeta = $this->bag->getSocketMetaData($this->socket);
        if ($isReadOnly) {
            self::assertSame(
                $originalMeta[ $key ],
                $newMeta[ $key ],
                'Read-only metadata ' . $phpName . ' has been changed, but mustn\'t'
            );
        } else {
            self::assertNotSame(
                $originalMeta[ $key ],
                $newMeta[ $key ],
                'Writable value ' . $phpName . ' has not been modified, but must'
            );
        }
    }

    /**
     * testGetSetSocketOperation
     *
     * @return void
     */
    public function testGetSetSocketOperation()
    {
        $this->bag->addSocket(
            $this->socket,
            $this->operation
        );

        self::assertSame(
            $this->operation,
            $this->bag->getSocketOperation($this->socket),
            'Incorrect initial operation'
        );

        $operation = clone $this->operation;
        $this->bag->setSocketOperation($this->socket, $operation);

        self::assertSame(
            $operation,
            $this->bag->getSocketOperation($this->socket),
            'Incorrect set operation'
        );
    }

    /**
     * testCantOperateNonAddedSocket
     *
     * @return void
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage Trying to perform operation on not added socket.
     */
    public function testCantOperateNonAddedSocket()
    {
        $this->bag->getSocketMetaData($this->socket);
    }

    /**
     * metadataKeysDataProvider
     *
     * @return array
     */
    public function metadataKeysDataProvider()
    {
        static $metadata;
        if ($metadata === null) {
            $readOnlyKeys = [
                RequestExecutorInterface::META_REQUEST_COMPLETE       => 1,
                RequestExecutorInterface::META_CONNECTION_FINISH_TIME => 1,
                RequestExecutorInterface::META_CONNECTION_START_TIME  => 1,
                RequestExecutorInterface::META_LAST_IO_START_TIME     => 1,
                RequestExecutorInterface::META_BYTES_SENT             => 1,
                RequestExecutorInterface::META_BYTES_RECEIVED         => 1,
                RequestExecutorInterface::META_RECEIVE_SPEED          => 1,
            ];

            $metadata = [ ];
            $ref      = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutorInterface');
            foreach ($ref->getConstants() as $name => $value) {
                if (!preg_match('#META_.*?#', $name)) {
                    continue;
                }

                $metadata[] = [ $name, $value, isset($readOnlyKeys[ $value ]) ];
            }
        }

        return $metadata;
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->executor = $this->getMock(
            'AsyncSockets\RequestExecutor\RequestExecutorInterface',
            [
                'isExecuting',
                'socketBag',
                'withEventHandler',
                'withLimitationSolver',
                'executeRequest',
                'stopRequest',
            ]
        );
        $this->operation = $this->getMock('AsyncSockets\Operation\OperationInterface');
        $this->socket    = $this->getMock('AsyncSockets\Socket\SocketInterface');

        $this->connectTimeout = (double) mt_rand(1, PHP_INT_MAX);
        $this->ioTimeout      = (double) mt_rand(1, PHP_INT_MAX);
        $this->bag            = new SocketBag($this->executor, $this->connectTimeout, $this->ioTimeout);
    }
}
