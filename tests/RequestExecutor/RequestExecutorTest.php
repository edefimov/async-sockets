<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\RequestExecutor\RequestExecutor;
use Tests\AsyncSockets\Socket\FileSocket;

/**
 * Class RequestExecutorTest
 */
class RequestExecutorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Amount of sockets to test
     */
    const COUNT_TEST_SOCKETS = 10;

    /**
     * List of test objects
     *
     * @var FileSocket
     */
    private $socket;

    /**
     * RequestExecutor
     *
     * @var RequestExecutor
     */
    private $executor;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket   = new FileSocket();
        $this->executor = new RequestExecutor();
    }

    /**
     * testCantSetHandlerOnNonAddedSocket
     *
     * @return void
     * @expectedException \OutOfBoundsException
     */
    public function testCantSetHandlerOnNonAddedSocket()
    {
        $this->executor->addHandler([
            EventType::CONNECTED => function () {
            }
        ], $this->socket);
    }

    /**
     * testAddSocket
     *
     * @return void
     * @depends testCantSetHandlerOnNonAddedSocket
     */
    public function testAddSocket()
    {
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_READ, []);
        $this->executor->addHandler([
            EventType::CONNECTED => function () {
            }
        ], $this->socket);
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
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_READ, []);
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_WRITE, []);
    }

    /**
     * testHasSocket
     *
     * @return void
     */
    public function testHasSocket()
    {
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_READ, []);
        self::assertTrue(
            $this->executor->hasSocket($this->socket),
            'hasSocket returned false for added socket'
        );

        self::assertFalse(
            $this->executor->hasSocket(new FileSocket()),
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
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_READ, []);
        $meta = $this->executor->getSocketMetaData($this->socket);
        $ref  = new \ReflectionClass($this->executor);
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
        $this->executor->addSocket($this->socket, RequestExecutor::OPERATION_READ, []);
        $this->executor->removeSocket($this->socket);
        self::assertFalse(
            $this->executor->hasSocket($this->socket),
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
        $this->executor->removeSocket($this->socket);
        self::assertFalse(
            $this->executor->hasSocket($this->socket),
            'hasSocket returned true for removed socket'
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
        $this->executor->addSocket(
            $this->socket,
            RequestExecutor::OPERATION_READ,
            [
                RequestExecutor::META_ADDRESS => 'php://temp'
            ]
        );

        $this->executor->addHandler([
            EventType::READ => function (Event $event) {
                self::assertSame($this->socket, $event->getSocket(), 'Unknown socket was passed in event');
                self::assertSame(
                    $this->executor,
                    $event->getExecutor(),
                    'Unexpected request executor is given in event'
                );

                $this->executor->removeSocket($event->getSocket());
            },
            EventType::EXCEPTION => function (SocketExceptionEvent $e) {
                throw $e->getException();
            }
        ], $this->socket);
        $this->executor->execute();

        self::fail('Event handler must have been executed');
    }

    /**
     * testMetadataCanChange
     *
     * @param string $phpName Name in php file
     * @param string $key Key in metadata array
     * @param bool   $isReadOnly Flag whether it is read only constant
     * @param string $initialOperation Initial operation
     *
     * @return void
     * @dataProvider metadataKeysDataProvider
     */
    public function testMetadataCanChange($phpName, $key, $isReadOnly, $initialOperation)
    {
        $this->executor->addSocket($this->socket, $initialOperation, []);
        $originalMeta = $this->executor->getSocketMetaData($this->socket);
        self::assertEquals(
            $initialOperation,
            $originalMeta[RequestExecutor::META_OPERATION],
            'Unexpected initial operation'
        );

        $this->executor->setSocketMetaData($this->socket, $key, mt_rand(1, PHP_INT_MAX));
        $newMeta = $this->executor->getSocketMetaData($this->socket);
        if ($isReadOnly) {
            self::assertSame(
                $originalMeta[$key],
                $newMeta[$key],
                'Read-only metadata ' . $phpName . ' has been changed, but mustn\'t'
            );
        } else {
            self::assertNotSame(
                $originalMeta[$key],
                $newMeta[$key],
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
                RequestExecutor::META_REQUEST_COMPLETE => 1,
                RequestExecutor::META_CONNECTION_FINISH_TIME => 1,
                RequestExecutor::META_CONNECTION_START_TIME => 1,
                RequestExecutor::META_LAST_IO_START_TIME => 1,
            ];

            $metadata = [];
            $ref = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutor');
            foreach ($ref->getConstants() as $name => $value) {
                if (!preg_match('#META_.*?#', $name)) {
                    continue;
                }

                $metadata[] = [$name, $value, isset($readOnlyKeys[$value]), RequestExecutor::OPERATION_READ ];
                $metadata[] = [$name, $value, isset($readOnlyKeys[$value]), RequestExecutor::OPERATION_WRITE ];
            }
        }

        return $metadata;
    }
}
