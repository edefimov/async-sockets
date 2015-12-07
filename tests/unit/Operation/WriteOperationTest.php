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
use AsyncSockets\Operation\WriteOperation;

/**
 * Class WriteOperationTest
 */
class WriteOperationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var WriteOperation
     */
    private $operation;

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertFalse($this->operation->hasData(), 'Incorrect data initial state');
        self::assertNull($this->operation->getData(), 'Incorrect data initial state');
        self::assertEquals(
            OperationInterface::OPERATION_WRITE,
            $this->operation->getType(),
            'Incorrect type for operation'
        );
    }

    /**
     * testSetData
     *
     * @return void
     */
    public function testSetData()
    {
        $data = md5(microtime());
        $this->operation->setData($data);
        self::assertEquals($data, $this->operation->getData(), 'Data are not set');
        self::assertTrue($this->operation->hasData(), 'Event must have data here');
    }

    /**
     * testClearData
     *
     * @return void
     * @depends testSetData
     */
    public function testClearData()
    {
        $data = md5(microtime());
        $this->operation->setData($data);
        $this->operation->clearData();
        self::assertFalse($this->operation->hasData(), 'Write buffer was not cleared');
        self::assertNull($this->operation->getData(), 'Strange data returned');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->operation = new WriteOperation();
    }
}
