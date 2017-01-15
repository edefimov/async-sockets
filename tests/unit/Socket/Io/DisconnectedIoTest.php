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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\Io\DisconnectedIo;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class DisconnectedIoTest
 */
class DisconnectedIoTest extends AbstractIoTest
{
    /**
     * testRead
     *
     * @param bool $isOutOfBand Is it out-of-band data
     *
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testRead($isOutOfBand)
    {
        $picker = $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface');
        /** @var FramePickerInterface $picker */
        $this->object->read($picker, $this->context, $isOutOfBand);
    }

    /**
     * testWrite
     *
     * @param bool $isOutOfBand Flag if data are out of band
     *
     * @return void
     * @dataProvider boolDataProvider
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not start io operation on uninitialized socket.
     */
    public function testWrite($isOutOfBand)
    {
        $this->object->write('something', $this->context, $isOutOfBand);
    }

    /**
     * {@inheritdoc}
     */
    protected function createIoInterface(SocketInterface $socket)
    {
        return new DisconnectedIo($socket);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSocketInterface()
    {
        return $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
    }
}
