<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Socket\Io\Context;
use AsyncSockets\Socket\Io\IoInterface;
use AsyncSockets\Socket\SocketInterface;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class AbstractIoTest
 */
abstract class AbstractIoTest extends AbstractTestCase
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
     * Context
     *
     * @var Context
     */
    protected $context;

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
        $this->socket  = $this->createSocketInterface();
        $this->object  = $this->createIoInterface($this->socket);
        $this->context = new Context();
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
