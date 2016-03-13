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

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\Socket\AsyncSocketFactory;
use Tests\Application\Mock\PhpFunctionMocker;

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
        PhpFunctionMocker::getPhpFunctionMocker('extension_loaded')->setCallable(
            function ($extension) {
                return $extension === 'libevent' ? false : \extension_loaded($extension);
            }
        );

        $executor = $this->factory->createRequestExecutor();
        self::assertInstanceOf(
            'AsyncSockets\RequestExecutor\NativeRequestExecutor',
            $executor,
            'Strange object ' . get_class($executor) . ' was created'
        );

        $ref = new \ReflectionClass('AsyncSockets\RequestExecutor\NativeRequestExecutor');
        self::assertTrue(
            $ref->implementsInterface('AsyncSockets\RequestExecutor\RequestExecutorInterface'),
            'NativeRequestExecutor must implement RequestExecutorInterface'
        );
    }

    /**
     * testLibEventRequestExecutorIsCreated
     *
     * @return void
     */
    public function testLibEventRequestExecutorIsCreated()
    {
        if (!extension_loaded('libevent')) {
            self::markTestSkipped('To pass this test libevent extension must be loaded');
        }

        $executor = $this->factory->createRequestExecutor();
        self::assertInstanceOf(
            'AsyncSockets\RequestExecutor\LibEventRequestExecutor',
            $executor,
            'Strange object ' . get_class($executor) . ' was created'
        );

        $ref = new \ReflectionClass('AsyncSockets\RequestExecutor\LibEventRequestExecutor');
        self::assertTrue(
            $ref->implementsInterface('AsyncSockets\RequestExecutor\RequestExecutorInterface'),
            'LibEventRequestExecutor must implement RequestExecutorInterface'
        );
    }

    /**
     * testCreateSocket
     *
     * @param string $type Socket type
     * @param string $className Class name to check
     * @param array  $options Options for socket
     *
     * @return void
     * @dataProvider socketTypeDataProvider
     */
    public function testCreateSocket($type, $className, array $options = [])
    {
        $object = $this->factory->createSocket($type, $options);
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
     * testCreateRequestExecutorWithInvalidArgument
     *
     * @return void
     * @expectedException \InvalidArgumentException
     */
    public function testCreateRequestExecutorWithInvalidArgument()
    {
        $factory = new AsyncSocketFactory(
            new Configuration(
                [
                    'preferredEngines' => md5(microtime())
                ]
            )
        );

        $factory->createRequestExecutor();
    }

    /**
     * socketTypeDataProvider
     *
     * @return array
     */
    public function socketTypeDataProvider()
    {
        return [
            [AsyncSocketFactory::SOCKET_CLIENT, 'AsyncSockets\Socket\ClientSocket', []],
            [AsyncSocketFactory::SOCKET_SERVER, 'AsyncSockets\Socket\ServerSocket', []],
            [
                AsyncSocketFactory::SOCKET_CLIENT,
                'AsyncSockets\Socket\PersistentClientSocket',
                [
                    AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT => true
                ],
            ],
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
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('extension_loaded')->restoreNativeHandler();
    }
}
