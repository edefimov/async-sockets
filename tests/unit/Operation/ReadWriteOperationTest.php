<?php
/**
 * Async Sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Operation;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\ReadWriteOperation;
use AsyncSockets\Operation\WriteOperation;

/**
 * Class ReadWriteOperationTest
 */
class ReadWriteOperationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $r = new ReadOperation();
        $w = new WriteOperation();

        $object = new ReadWriteOperation(true, [ $r, $w ]);

        self::assertTrue($object->isReadFirst(), 'Incorrect read flag');
        self::assertSame($r, $object->getReadOperation(), 'Incorrect read operation');
        self::assertSame($w, $object->getWriteOperation(), 'Incorrect read operation');
    }

    /**
     * testCorrectTypes
     *
     * @param OperationInterface[] $operations    Nested operations
     * @param string[]             $expectedTypes Expected value of getTypes method
     *
     * @return void
     * @dataProvider typesDataProvider
     */
    public function testCorrectTypes(array $operations, array $expectedTypes)
    {
        $object = new ReadWriteOperation(true, $operations);
        $types  = $object->getTypes();

        sort($types);
        sort($expectedTypes);
        self::assertSame($expectedTypes, $types, 'Incorrect types returned');
    }

    /**
     * testCompletingOperations
     *
     * @return void
     */
    public function testCompletingOperations()
    {
        $r = new ReadOperation();
        $w = new WriteOperation();

        $object = new ReadWriteOperation(true, [ $r, $w ]);

        self::assertNotNull($object->getReadOperation());
        $object->markCompleted($r);
        self::assertNull($object->getReadOperation(), 'Read operation is not removed');

        self::assertNotNull($object->getWriteOperation());
        $object->markCompleted($w);
        self::assertNull($object->getWriteOperation(), 'Write operation is not removed');
    }

    /**
     * testThatInvalidOperationNotCompleteCorrect
     *
     * @return void
     */
    public function testThatInvalidOperationNotCompleteCorrect()
    {
        $r = new ReadOperation();
        $w = new WriteOperation();

        $object = new ReadWriteOperation(true, [ $r, $w ]);

        self::assertNotNull($object->getReadOperation());
        $object->markCompleted(new ReadOperation());
        self::assertNotNull($object->getReadOperation(), 'Read operation must be kept.');

        self::assertNotNull($object->getWriteOperation());
        $object->markCompleted(new WriteOperation());
        self::assertNotNull($object->getWriteOperation(), 'Write operation must be kept.');
    }

    /**
     * testProcessingReadQueue
     *
     * @return void
     */
    public function testProcessingReadQueue()
    {
        $queue = [];
        $count = 20;
        for ($i = 0; $i < $count; $i++) {
            $queue[$i] = new ReadOperation();
        }

        $object = new ReadWriteOperation(false, $queue);
        for ($i = 0; $i < $count; $i++) {
            self::assertSame($queue[$i], $object->getReadOperation(), 'Unexpected operation.');
            $object->markCompleted($queue[$i]);
        }
    }

    /**
     * testProcessingWriteQueue
     *
     * @return void
     */
    public function testProcessingWriteQueue()
    {
        $queue = [];
        $count = 20;
        for ($i = 0; $i < $count; $i++) {
            $queue[$i] = new WriteOperation();
        }

        $object = new ReadWriteOperation(false, $queue);
        for ($i = 0; $i < $count; $i++) {
            self::assertSame($queue[$i], $object->getWriteOperation(), 'Unexpected operation.');
            $object->markCompleted($queue[$i]);
        }
    }

    /**
     * testProcessingReadWriteQueue
     *
     * @return void
     */
    public function testProcessingReadWriteQueue()
    {
        $readQueue  = [];
        $writeQueue = [];
        $count      = 20;
        for ($i = 0; $i < $count; $i++) {
            $readQueue[$i]  = new ReadOperation();
            $writeQueue[$i] = new WriteOperation();
        }

        $object = new ReadWriteOperation(false, array_merge($readQueue, $writeQueue));
        for ($i = 0; $i < $count; $i++) {
            self::assertSame($readQueue[$i], $object->getReadOperation(), 'Unexpected operation.');
            $object->markCompleted($readQueue[$i]);

            self::assertSame($writeQueue[$i], $object->getWriteOperation(), 'Unexpected operation.');
            $object->markCompleted($writeQueue[$i]);
        }
    }

    /**
     * testThatSchedulingIncorrectOperationsDoNothing
     *
     * @return void
     */
    public function testThatSchedulingIncorrectOperationsDoNothing()
    {
        $o = $this->getMockBuilder('AsyncSockets\Operation\OperationInterface')->getMockForAbstractClass();

        $object = new ReadWriteOperation(true);
        $object->scheduleOperation($o);

        self::assertNull($object->getReadOperation(), 'Incorrect read operation added');
        self::assertNull($object->getWriteOperation(), 'Incorrect write operation added');
    }

    /**
     * testThatCompletingIncorrectOperationsDoNothing
     *
     * @return void
     */
    public function testThatCompletingIncorrectOperationsDoNothing()
    {
        $r = new ReadOperation();
        $w = new WriteOperation();


        $o = $this->getMockBuilder('AsyncSockets\Operation\OperationInterface')->getMockForAbstractClass();

        $object = new ReadWriteOperation(true, [$r, $w]);
        $object->markCompleted($o);

        self::assertSame($r, $object->getReadOperation(), 'Incorrect read operation removed');
        self::assertSame($w, $object->getWriteOperation(), 'Incorrect write operation removed');
    }

    /**
     * typesDataProvider
     *
     * @return array
     */
    public function typesDataProvider()
    {
        return [
            [ [new ReadOperation()], [OperationInterface::OPERATION_READ] ],
            [ [new WriteOperation()], [OperationInterface::OPERATION_WRITE] ],
            [
                [new ReadOperation() , new WriteOperation()],
                [OperationInterface::OPERATION_WRITE, OperationInterface::OPERATION_READ]
            ],
        ];
    }
}
