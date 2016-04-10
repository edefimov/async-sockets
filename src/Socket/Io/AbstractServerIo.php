<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Exception\NetworkSocketException;

/**
 * Class AbstractServerIo
 */
abstract class AbstractServerIo extends AbstractIo
{
    /** {@inheritdoc} */
    public function write($data)
    {
        throw new NetworkSocketException($this->socket, 'Can not write data to tcp/udp server socket.');
    }
}
