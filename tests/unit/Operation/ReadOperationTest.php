<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Operation;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;

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
            'AsyncSockets\Frame\NullFramePicker',
            $this->operation->getFramePicker(),
            'Incorrect initial state for framePicker'
        );
        self::assertSame(
            OperationInterface::OPERATION_READ,
            $this->operation->getType(),
            'Incorrect type for operation'
        );
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->operation = new ReadOperation();
    }
}
