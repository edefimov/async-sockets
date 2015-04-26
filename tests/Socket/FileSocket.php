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

use AsyncSockets\Socket\AbstractSocket;

/**
 * Class FileSocket
 */
class FileSocket extends AbstractSocket
{
    /** {@inheritdoc} */
    protected function createSocketResource(
        $address,
        $context
    ) {
        return fopen($address, 'rw');
    }
}
