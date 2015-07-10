<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Socket\Io\IoInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class AbstractIoTest
 */
abstract class AbstractIoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var IoInterface
     */
    protected $object;

    /**
     * Socket object for testing
     *
     * @var SocketInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $socket;

    /**
     * Create test object
     *
     * @param SocketInterface $socket Socket object
     *
     * @return IoInterface
     */
    abstract protected function createIoInterface(SocketInterface $socket);

    /**
     * Create implementation of SocketInterface
     *
     * @return SocketInterface
     * @see SocketInterface
     */
    abstract protected function createSocketInterface();

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket = $this->createSocketInterface();
        $this->object = $this->createIoInterface($this->socket);
    }

    /**
     * Create string with random IP address
     *
     * @return string
     */
    protected function randomIpAddress()
    {
        return sprintf(
            '%d.%d.%d.%d:%d',
            mt_rand(0, 255),
            mt_rand(0, 255),
            mt_rand(0, 255),
            mt_rand(0, 255),
            mt_rand(1, 65535)
        );
    }
}
