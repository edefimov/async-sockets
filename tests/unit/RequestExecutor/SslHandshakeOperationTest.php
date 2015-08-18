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

use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\SslHandshakeOperation;

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
        self::assertEquals(
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
        $mock       = $this->getMockForAbstractClass('AsyncSockets\RequestExecutor\OperationInterface');
        $cipher    = mt_rand(0, PHP_INT_MAX);
        $operation = new SslHandshakeOperation($cipher, $mock);
        self::assertSame($cipher, $operation->getCipher(), 'Incorrect cipher');
        self::assertSame($mock, $operation->getNextOperation(), 'Incorrect operation');
    }
}
