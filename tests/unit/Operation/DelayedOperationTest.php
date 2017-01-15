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

use AsyncSockets\Operation\DelayedOperation;

/**
 * Class DelayedOperationTest
 */
class DelayedOperationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $mock     = $this->getMockBuilder('AsyncSockets\Operation\OperationInterface')
                        ->setMethods([ 'getType'])
                        ->getMockForAbstractClass();
        $callable = [$this, __FUNCTION__];

        $type = sha1(microtime(true));
        $mock->expects(self::any())
            ->method('getType')
            ->willReturn($type);
        $args = [
            time() => mt_rand(1, PHP_INT_MAX)
        ];
        $object = new DelayedOperation($mock, $callable, $args);
        self::assertSame($callable, $object->getCallable(), 'Incorrect callable function');
        self::assertSame($mock, $object->getOriginalOperation(), 'Incorrect original operation');
        self::assertSame($type, $object->getType(), 'Incorrect operation type');
        self::assertSame($args, $object->getArguments(), 'Incorrect arguments');
    }
}
