<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Socket\AcceptedSocket;

/**
 * Class AcceptedSocketTest
 */
class AcceptedSocketTest extends ClientSocketTest
{
    /** {@inheritdoc} */
    protected function createSocketInterface()
    {
        return new AcceptedSocket(fopen('php://temp', 'r+'));
    }

    /**
     * testExceptionWillBeThrowsOnCreateFailWithErrNo
     *
     * @return void
     */
    public function testExceptionWillBeThrowsOnCreateFailWithErrNo()
    {
        // not applicable for AcceptedSocket
    }

    /**
     * testExceptionWillBeThrowsOnCreateFail
     *
     * @return void
     * @expectedException \AsyncSockets\Exception\NetworkSocketException
     * @expectedExceptionMessage Remote client socket can not be reopened.
     */
    public function testExceptionWillBeThrowsOnCreateFail()
    {
        $this->socket->open('php://temp');
        $this->socket->open('php://temp');
    }
}
