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

use AsyncSockets\RequestExecutor\OperationInterface;
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
     * RequestExecutorInterface
     *
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $executor;

    /**
     * MOcked operation
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
        $this->bag->addSocket(
            $this->socket,
            $this->operation,
            [ ],
            $this->getMock('AsyncSockets\RequestExecutor\EventInvocationHandlerInterface')
        );
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
                'getSocketBag',
                'setEventInvocationHandler',
                'setLimitationDecider',
                'executeRequest',
                'stopRequest',
            ]
        );
        $this->operation = $this->getMock('AsyncSockets\RequestExecutor\OperationInterface');
        $this->socket    = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $this->bag       = new SocketBag($this->executor);
    }
}
