<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Socket\AsyncSocketFactory;
use Tests\AsyncSockets\Mock\PhpFunctionMocker;

/**
 * Class AsyncSocketFactoryTest
 */
class AsyncSocketFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * AsyncSocketFactory
     *
     * @var AsyncSocketFactory
     */
    private $factory;

    /**
     * testSimpleRequestExecutorCreated
     *
     * @return void
     */
    public function testSimpleRequestExecutorCreated()
    {
        $executor = $this->factory->createRequestExecutor();
        self::assertInstanceOf(
            'AsyncSockets\RequestExecutor\RequestExecutor',
            $executor,
            'Strange object ' . get_class($executor) . ' was created'
        );

        $ref = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutor');
        self::assertTrue(
            $ref->implementsInterface('AsyncSockets\RequestExecutor\RequestExecutorInterface'),
            'RequestExecutor must implement RequestExecutorInterface'
        );
    }

    /**
     * testCreateSocket
     *
     * @param string $type Socket type
     * @param string $className Class name to check
     *
     * @return void
     * @dataProvider socketTypeDataProvider
     */
    public function testCreateSocket($type, $className)
    {
        $object = $this->factory->createSocket($type);
        self::assertInstanceOf(
            $className,
            $object,
            'Expected object of class ' . $className . ', but ' . get_class($object) . ' received for type ' . $type
        );

        $ref = new \ReflectionClass($className);
        self::assertTrue(
            $ref->implementsInterface('AsyncSockets\Socket\SocketInterface'),
            'Socket of type ' . $type . ' must implement SocketInterface'
        );
    }

    /**
     * testCreateSocketWithInvalidArgument
     *
     * @return void
     * @expectedException \InvalidArgumentException
     */
    public function testCreateSocketWithInvalidArgument()
    {
        $this->factory->createSocket(md5(microtime()));
    }

    /**
     * socketTypeDataProvider
     *
     * @return array
     */
    public function socketTypeDataProvider()
    {
        return [
            [AsyncSocketFactory::SOCKET_CLIENT, 'AsyncSockets\Socket\ClientSocket'],
            [AsyncSocketFactory::SOCKET_SERVER, 'AsyncSockets\Socket\ServerSocket'],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->factory = new AsyncSocketFactory();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        PhpFunctionMocker::getPhpFunctionMocker('interface_exists')->restoreNativeHandler();
    }
}
