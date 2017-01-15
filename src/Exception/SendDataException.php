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
 * Class SendDataException. Thrown any time there is a problem with sending data to remote side
 */
class SendDataException extends TransferException
{
    /**
     * Creates data sending failure
     *
     * @param SocketInterface $socket Socket with error
     *
     * @return SendDataException
     */
    public static function failedToSendData(SocketInterface $socket)
    {
        return new self($socket, 'Failed to send data.');
    }

    /**
     * @inheritDoc
     */
    public function __construct(
        SocketInterface $socket,
        $message = '',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($socket, self::DIRECTION_SEND, $message, $code, $previous);
    }
}
