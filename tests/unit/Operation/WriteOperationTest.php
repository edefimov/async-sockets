<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
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
        self::assertFalse($this->operation->isOutOfBand(), 'Incorrect out-of-band initial state');
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
        self::assertTrue($this->operation->hasData(), 'Operation must have data here');

        $this->operation->setOutOfBand(true);
        self::assertTrue($this->operation->isOutOfBand(), 'The out-of-band flag is not changed');
        $this->operation->setOutOfBand(false);
        self::assertFalse($this->operation->isOutOfBand(), 'The out-of-band flag is not changed');
    }

    /**
     * testConstructorParameters
     *
     * @param string $data Data for operation
     * @param bool   $isOutOfBand Out of band flag
     *
     * @return void
     * @dataProvider constructorParametersDataProvider
     */
    public function testConstructorParameters($data, $isOutOfBand)
    {
        $operation = new WriteOperation($data, $isOutOfBand);
        self::assertSame($data, $operation->getData(), 'Data are not set');
        self::assertTrue($operation->hasData(), 'Operation must have data here');
        self::assertSame($isOutOfBand, $operation->isOutOfBand(), 'Incorrect out of band flag');
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

    /**
     * constructorParametersDataProvider
     *
     * @return array
     */
    public function constructorParametersDataProvider()
    {
        return [
            [sha1(microtime(true)), true],
            [sha1(microtime(true) + mt_rand(0, PHP_INT_MAX)), false],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->operation = new WriteOperation();
    }
}
