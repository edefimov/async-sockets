<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class AbstractServerIoTest
 */
abstract class AbstractServerIoTest extends AbstractIoTest
{
    /**
     * prepareForTestResponseStructureIsValid
     *
     * @param string   &$remoteAddress Remote socket address
     * @param resource &$remoteResource Remote socket resource
     *
     * @return void
     */
    abstract protected function prepareForTestResponseStructureIsValid(&$remoteAddress, &$remoteResource);

    /**
     * testCantWriteToServerSocket
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Can not write data to tcp/udp server socket.
     */
    public function testCantWriteToServerSocket()
    {
        $this->object->write('data');
    }

    /**
     * testResponseStructureIsValid
     *
     * @return AcceptedFrame
     */
    public function testResponseStructureIsValid()
    {
        $remoteAddress  = null;
        $remoteResource = null;

        $this->prepareForTestResponseStructureIsValid($remoteAddress, $remoteResource);

        $picker = $this->getMockForAbstractClass('AsyncSockets\Frame\FramePickerInterface');
        /** @var FramePickerInterface $picker */
        $frame = $this->object->read($picker);

        /** @var AcceptedFrame $frame */
        self::assertInstanceOf('AsyncSockets\Frame\AcceptedFrame', $frame, 'Invalid frame created');
        self::assertEquals($remoteAddress, (string) $frame, 'Invalid frame data');
        self::assertNotNull($remoteResource, 'Remote resource was not created');
        $frame->getClientSocket()->open('');
        self::assertSame($remoteResource, $frame->getClientSocket()->getStreamResource(), 'Unexpected resource.');

        return $frame;
    }
}
