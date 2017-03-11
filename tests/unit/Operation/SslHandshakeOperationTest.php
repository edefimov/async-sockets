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
use AsyncSockets\Operation\SslHandshakeOperation;

/**
 * Class SslHandshakeOperationTest
 */
class SslHandshakeOperationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        $operation = new SslHandshakeOperation();
        self::assertSame(STREAM_CRYPTO_METHOD_TLS_CLIENT, $operation->getCipher(), 'Incorrect initial cipher');
        self::assertNull($operation->getNextOperation(), 'Incorrect next operation initial state');
        self::assertSame(
            OperationInterface::OPERATION_WRITE,
            $operation->getType(),
            'Incorrect type for operation'
        );
    }

    /**
     * testConstructorParams
     *
     * @return void
     */
    public function testConstructorParams()
    {
        $mock      = $this->getMockBuilder('AsyncSockets\Operation\OperationInterface')
                            ->getMockForAbstractClass();
        $cipher    = mt_rand(0, PHP_INT_MAX);
        $operation = new SslHandshakeOperation($mock, $cipher);
        self::assertSame($cipher, $operation->getCipher(), 'Incorrect cipher');
        self::assertSame($mock, $operation->getNextOperation(), 'Incorrect operation');
    }
}
