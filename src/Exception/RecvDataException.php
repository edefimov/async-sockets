<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Exception;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class RecvDataException. Thrown any time there is error during data receiving process
 */
class RecvDataException extends TransferException
{
    /**
     * @inheritDoc
     */
    public function __construct(
        SocketInterface $socket,
        $message = '',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($socket, self::DIRECTION_RECV, $message, $code, $previous);
    }
}
