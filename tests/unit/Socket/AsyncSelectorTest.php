<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\SocketInterface;
use Tests\Application\Mock\PhpFunctionMocker;

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
     * @var SocketInterface
     */
    private $socket;

    /**
     * Socket resource for test
     *
     * @var resource
     */
    private $socketResource;

    /**
     * AsyncSelector
     *
     * @var AsyncSelector
     */
    private $selector;

    /**
     * Test that socket object will be returned in read context property
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     */
    public function testSelectReadWrite($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperation($this->socket, $operation);
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * testAddSocketArrayReadWrite
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider socketOperationDataProvider
     */
    public function testAddSocketArrayReadWrite($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperationArray([ $this->socket ], $operation);
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * testAddSocketArrayWrite
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @depends      testAddSocketArrayReadWrite
     * @dataProvider socketOperationDataProvider
     */
    public function testAddSocketArrayReadWriteComplexArray($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperationArray(
            [
                [ $this->socket, $operation ],
            ]
        );
        $this->verifySocketSelectOperation($countRead, $countWrite);
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
     * testAddSocketArrayWithInvalidArrayStructure
     *
     * @param array $socketData Socket add getData
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider invalidSocketAddDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testAddSocketArrayWithInvalidArrayStructure(array $socketData)
    {
        $this->selector->addSocketOperationArray($socketData);
    }

    /**
     * testRemoveSocket
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider socketOperationDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveSocket($operation)
    {
        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->removeSocketOperation($this->socket, $operation);
        $this->selector->select(0);
    }

    /**
     * testStreamSelectFail
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \AsyncSockets\Exception\SocketException
     */
    public function testStreamSelectFail($operation)
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(
            function () {
                return false;
            }
        );

        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->select(0);
    }

    /**
     * testTimeOutExceptionWillBeThrown
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @dataProvider socketOperationDataProvider
     * @expectedException \AsyncSockets\Exception\TimeoutException
     */
    public function testTimeOutExceptionWillBeThrown($operation)
    {
        $mocker = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $mocker->setCallable(
            function (array &$read = null, array &$write = null) {
                $read  = [ ];
                $write = [ ];

                return 0;
            }
        );

        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->select(0);
    }

    /**
     * testRemoveAllSocketOperations
     *
     * @param string $operation Operation to execute
     *
     * @return void
     * @depends      testSelectReadWrite
     * @dataProvider socketOperationDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveAllSocketOperations($operation)
    {
        $this->selector->addSocketOperation($this->socket, $operation);
        $this->selector->removeAllSocketOperations($this->socket);
        $this->selector->select(0);
    }

    /**
     * testChangeSocketOperation
     *
     * @param string $operation Operation to execute
     * @param int    $countRead Amount of sockets, that must be ready to read
     * @param int    $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     * @depends      testRemoveAllSocketOperations
     * @dataProvider socketOperationDataProvider
     */
    public function testChangeSocketOperation($operation, $countRead, $countWrite)
    {
        $this->selector->addSocketOperationArray(
            [
                [ $this->socket, OperationInterface::OPERATION_READ ],
                [ $this->socket, OperationInterface::OPERATION_WRITE ],
            ]
        );

        $this->selector->changeSocketOperation($this->socket, $operation);
        $this->verifySocketSelectOperation($countRead, $countWrite);
    }

    /**
     * testThatIfSelectFailedIncompleteSleepWillBeCalled
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\TimeoutException
     */
    public function testThatIfSelectFailedIncompleteSleepWillBeCalled()
    {
        $usleep = $this->getMock('Countable', [ 'count' ]);
        $usleep->expects(self::exactly(AsyncSelector::ATTEMPT_COUNT_FOR_INFINITE_TIMEOUT - 1))
            ->method('count')
            ->with(AsyncSelector::ATTEMPT_DELAY);

        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return 1;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return false;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('usleep')->setCallable([ $usleep, 'count' ]);

        $this->selector->addSocketOperation($this->socket, OperationInterface::OPERATION_READ);
        $this->selector->select(0, 2 * AsyncSelector::ATTEMPT_DELAY);
    }

    /**
     * testReadForIoWithServerSocket
     *
     * @return void
     */
    public function testReadForIoWithServerSocket()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return false;
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->setCallable(
            function () {
                return $this->socket->getStreamResource();
            }
        );

        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->setCallable(
            function ($resource, $wantPeer) {
                if ($resource !== $this->socket->getStreamResource()) {
                    return \stream_socket_get_name($resource, $wantPeer);
                }

                return $wantPeer ? null : '127.0.0.1:35424';
            }
        );

        $this->selector->addSocketOperation($this->socket, OperationInterface::OPERATION_READ);
        $context = $this->selector->select(null);
        self::assertCount(1, $context->getRead(), 'Server socket was not returned');
    }

    /**
     * socketOperationDataProvider
     *
     * @return array
     */
    public function socketOperationDataProvider()
    {
        // form: operation, ready to read, ready to write
        return [
            [ OperationInterface::OPERATION_READ, 1, 0 ],
            [ OperationInterface::OPERATION_WRITE, 0, 1 ],
        ];
    }

    /**
     * invalidSocketAddDataProvider
     *
     * @return array
     */
    public function invalidSocketAddDataProvider()
    {
        return [
            [ [ [ $this->socket ] ] ],
            [ [ $this->socket ] ],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socketResource = fopen('php://temp', 'r+');
        $this->socket         = $this->getMockForAbstractClass(
            'AsyncSockets\Socket\SocketInterface',
            [ ],
            '',
            false,
            true,
            true,
            [ 'getStreamResource' ]
        );
        $this->socket->expects(self::any())->method('getStreamResource')->willReturn($this->socketResource);

        $this->selector = new AsyncSelector();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_get_name')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('usleep')->restoreNativeHandler();
        $this->socket->close();
    }

    /**
     * Check validity of select operation
     *
     * @param int $countRead Amount of sockets, that must be ready to read
     * @param int $countWrite Amount of sockets, that must be ready to write
     *
     * @return void
     */
    private function verifySocketSelectOperation($countRead, $countWrite)
    {
        PhpFunctionMocker::getPhpFunctionMocker('stream_socket_recvfrom')->setCallable(
            function () {
                return '';
            }
        );
        $result = $this->selector->select(0);
        self::assertCount($countRead, $result->getRead(), 'Unexpected result of read selector');
        self::assertCount($countWrite, $result->getWrite(), 'Unexpected result of write selector');
        $testSocket = $result->getRead() + $result->getWrite();
        self::assertSame($this->socket, $testSocket[ 0 ], 'Unexpected object returned for operation');
    }
}
