<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Exception\SslHandshakeException;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Operation\SslHandshakeOperation;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SslHandshakeIoHandler
 */
class SslHandshakeIoHandler implements IoHandlerInterface
{
    /** {@inheritdoc} */
    public function supports(OperationInterface $operation)
    {
        return $operation instanceof SslHandshakeOperation;
    }

    /** {@inheritdoc} */
    public function handle(
        OperationInterface $operation,
        SocketInterface $socket,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    ) {
        if (!($operation instanceof SslHandshakeOperation)) {
            throw new \LogicException(
                'Can not use ' . get_class($this) . ' for ' . get_class($operation) . ' operation'
            );
        }

        $resource = $socket->getStreamResource();
        $result   = stream_socket_enable_crypto($resource, true, $operation->getCipher());
        if ($result === true) {
            return $operation->getNextOperation();
        } elseif ($result === false) {
            throw new SslHandshakeException($socket, 'SSL handshake failed.');
        }

        return $operation;
    }
}
