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

use AsyncSockets\Socket\Io\DisconnectedIo;

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
        $this->object->read(
            $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface')
        );
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
        $this->object = new DisconnectedIo(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );
    }
}
