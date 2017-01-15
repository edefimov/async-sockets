<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class AcceptedFrameTest
 */
class AcceptedFrameTest extends FrameTest
{
    /**
     * Socket for frame
     *
     * @var SocketInterface
     */
    private $socket;

    /** {@inheritdoc} */
    protected function createFrame($data)
    {
        return new AcceptedFrame($data, $this->socket);
    }

    /**
     * testValidSocketIsReturned
     *
     * @return void
     */
    public function testValidSocketIsReturned()
    {
        $clientAddress = '127.0.0.1:12345';
        $frame         = $this->createFrame($clientAddress);
        self::assertSame($this->socket, $frame->getClientSocket(), 'Incorrect socket');
        self::assertEquals($clientAddress, $frame->getRemoteAddress(), 'Incorrect socket');
        self::assertEquals(
            $frame->getRemoteAddress(),
            (string) $frame,
            'String casting for AcceptedFrame must return client address'
        );
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->socket = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
    }
}
