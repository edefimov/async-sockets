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

use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ReadOperationTest
 */
class ReadOperationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var ReadOperation
     */
    private $operation;

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertInstanceOf(
            'AsyncSockets\Frame\NullFrame',
            $this->operation->getFrame(),
            'Incorrect initial state for frame'
        );
        self::assertEquals(
            RequestExecutorInterface::OPERATION_READ,
            $this->operation->getType(),
            'Incorrect type for operation'
        );
    }

    /**
     * testSetData
     *
     * @return void
     */
    public function testSetFrame()
    {
        $frame = $this->getMock('AsyncSockets\Frame\FrameInterface');
        $this->operation->setFrame($frame);
        self::assertSame($frame, $this->operation->getFrame(), 'Frame is not set');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->operation = new ReadOperation();
    }
}
