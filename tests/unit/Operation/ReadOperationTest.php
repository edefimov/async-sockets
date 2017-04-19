<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
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
            'AsyncSockets\Frame\RawFramePicker',
            $this->operation->getFramePicker(),
            'Incorrect initial state for framePicker'
        );

        $types = $this->operation->getTypes();
        self::assertCount(1, $types, 'Unexpected type count');
        self::assertSame(OperationInterface::OPERATION_READ, reset($types), 'Incorrect operation type');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->operation = new ReadOperation();
    }
}
