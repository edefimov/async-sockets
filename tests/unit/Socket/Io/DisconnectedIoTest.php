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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\Io\DisconnectedIo;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class DisconnectedIoTest
 */
class DisconnectedIoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var DisconnectedIo
     */
    private $object;

    /**
     * testRead
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testRead()
    {
        $picker = $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface');
        /** @var FramePickerInterface $picker */
        $this->object->read($picker);
    }

    /**
     * testWrite
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testWrite()
    {
        $this->object->write('something');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $socket = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
        /** @var SocketInterface $socket */
        $this->object = new DisconnectedIo($socket);
    }
}
