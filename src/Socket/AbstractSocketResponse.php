<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket;

/**
 * Class AbstractSocketResponse
 */
abstract class AbstractSocketResponse implements SocketResponseInterface
{
    /** {@inheritdoc} */
    public function __toString()
    {
        return $this->getData();
    }
}
