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

use AsyncSockets\Operation\NullOperation;
use AsyncSockets\Operation\OperationInterface;

/**
 * Class NullOperationTest
 */
class NullOperationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $operation = new NullOperation();

        $types = $operation->getTypes();
        self::assertCount(1, $types, 'Unexpected type count');
        self::assertSame(OperationInterface::OPERATION_READ, reset($types), 'Incorrect operation type');
    }
}
