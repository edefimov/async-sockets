<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\Socket;

use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class AsyncSelectorTest
 *
 * @SuppressWarnings("unused")
 * @SuppressWarnings("TooManyMethods")
 */
class AsyncSelectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test socket
     *
     * @var FileSocket
     */
    private $socket;

    /**
     * AsyncSelector
     *
     * @var AsyncSelector
     */
    private $selector;

    /**
     * Test that socket object will be returned in read context property
     *
     * @return void
     */
    public function testSelectRead()
    {
        $this->selector->addSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $result = $this->selector->select(0);
        self::assertCount(1, $result->getRead(), 'Unexpected result of read selector');
        self::assertCount(0, $result->getWrite(), 'Unexpected result of write selector');
        self::assertSame($this->socket, $result->getRead()[0], 'Unexpected object returned for read operation');
    }

    /**
     * Test that socket object will be returned in read context property
     *
     * @return void
     */
    public function testSelectWrite()
    {
        $this->selector->addSocketOperation($this->socket, RequestExecutorInterface::OPERATION_WRITE);
        $result = $this->selector->select(0);
        self::assertCount(1, $result->getWrite(), 'Unexpected result of write selector');
        self::assertCount(0, $result->getRead(), 'Unexpected result of read selector');
        self::assertSame($this->socket, $result->getWrite()[0], 'Unexpected object returned for write operation');
    }

    /**
     * testExceptionOnEmptySocketWhenSelect
     *
     * @return void
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnEmptySocketWhenSelect()
    {
        $this->selector->select(0);
    }

    /**
     * testAddSocketArrayRead
     *
     * @return void
     * @depends testSelectRead
     * @depends testSelectWrite
     */
    public function testAddSocketArrayRead()
    {
        $this->selector->addSocketOperationArray([$this->socket], RequestExecutorInterface::OPERATION_READ);
        $result = $this->selector->select(0);
        self::assertCount(1, $result->getRead(), 'Unexpected result of read selector');
        self::assertCount(0, $result->getWrite(), 'Unexpected result of write selector');
        self::assertSame($this->socket, $result->getRead()[0], 'Unexpected object returned for read operation');
    }

    /**
     * testAddSocketArrayWithInvalidArrayStructure1
     *
     * @return void
     * @depends testSelectRead
     * @depends testSelectWrite
     * @expectedException \InvalidArgumentException
     */
    public function testAddSocketArrayWithInvalidArrayStructure1()
    {
        $this->selector->addSocketOperationArray([ [$this->socket] ]);
    }

    /**
     * testAddSocketArrayWithInvalidArrayStructure2
     *
     * @return void
     * @depends testSelectRead
     * @depends testSelectWrite
     * @expectedException \InvalidArgumentException
     */
    public function testAddSocketArrayWithInvalidArrayStructure2()
    {
        $this->selector->addSocketOperationArray([ $this->socket ]);
    }

    /**
     * testAddSocketArrayWrite
     *
     * @return void
     * @depends testSelectRead
     * @depends testSelectWrite
     */
    public function testAddSocketArrayWrite()
    {
        $this->selector->addSocketOperationArray([
            [ $this->socket, RequestExecutorInterface::OPERATION_WRITE ]
        ]);

        $result = $this->selector->select(0);
        self::assertCount(1, $result->getWrite(), 'Unexpected result of write selector');
        self::assertCount(0, $result->getRead(), 'Unexpected result of read selector');
        self::assertSame($this->socket, $result->getWrite()[0], 'Unexpected object returned for write operation');
    }

    /**
     * testRemoveSocket
     *
     * @return void
     * @depends testSelectRead
     * @depends testSelectWrite
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveSocket()
    {
        $this->selector->addSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $this->selector->removeSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $this->selector->select(0);
    }

    /**
     * testStreamSelectFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\SocketException
     */
    public function testStreamSelectFail()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(function () {
            return false;
        });

        $this->selector->addSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $this->selector->select(0);
    }

    /**
     * testTimeOutExceptionWillBeThrown
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\TimeoutException
     */
    public function testTimeOutExceptionWillBeThrown()
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(function (array &$read = null, array &$write = null) {
            $read  = [];
            $write = [];
            return 0;
        });

        $this->selector->addSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $this->selector->select(0);
    }

    /**
     * testRemoveAllSocketOperations
     *
     * @return void
     * @depends testSelectRead
     * @depends testSelectWrite
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveAllSocketOperations()
    {
        $this->selector->addSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $this->selector->removeAllSocketOperations($this->socket);
        $this->selector->select(0);
    }

    /**
     * testChangeSocketOperation
     *
     * @return void
     * @depends testRemoveAllSocketOperations
     */
    public function testChangeSocketOperation()
    {
        $this->selector->addSocketOperationArray([
            [$this->socket, RequestExecutorInterface::OPERATION_READ],
            [$this->socket, RequestExecutorInterface::OPERATION_WRITE],
        ]);

        $this->selector->changeSocketOperation($this->socket, RequestExecutorInterface::OPERATION_READ);
        $result = $this->selector->select(0);
        self::assertCount(1, $result->getRead(), 'Unexpected result of read selector');
        self::assertCount(0, $result->getWrite(), 'Unexpected result of write selector');
        self::assertSame($this->socket, $result->getRead()[0], 'Unexpected object returned for read operation');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket   = new FileSocket();
        $this->socket->open('php://temp');
        $this->socket->setBlocking(false);

        $this->selector = new AsyncSelector();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        $this->socket->close();
    }
}
