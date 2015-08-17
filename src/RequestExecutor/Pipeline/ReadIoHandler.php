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

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class ReadIoHandler
 */
class ReadIoHandler implements IoHandlerInterface
{
    /** {@inheritdoc} */
    public function supports(OperationInterface $operation)
    {
        return $operation instanceof ReadOperation;
    }

    /** {@inheritdoc} */
    public function handle(
        OperationInterface $operation,
        SocketInterface $socket,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    ) {
        if (!($operation instanceof ReadOperation)) {
            throw new \LogicException(
                'Can not use ' . get_class($this) . ' for ' . get_class($operation) . ' operation'
            );
        }

        $meta    = $executor->socketBag()->getSocketMetaData($socket);
        $context = $meta[RequestExecutorInterface::META_USER_CONTEXT];

        try {
            /** @var ReadOperation $operation */
            $response = $socket->read($operation->getFramePicker());
            switch (true) {
                case $response instanceof PartialFrame:
                    return $operation;
                case $response instanceof AcceptedFrame:
                    $event = new AcceptEvent(
                        $executor,
                        $socket,
                        $context,
                        $response->getClientSocket(),
                        $response->getClientAddress()
                    );

                    $eventHandler->invokeEvent($event);
                    return new ReadOperation();
                default:
                    $event = new ReadEvent(
                        $executor,
                        $socket,
                        $context,
                        $response
                    );

                    $eventHandler->invokeEvent($event);
                    return $event->getNextOperation();
            }
        } catch (AcceptException $e) {
            return new ReadOperation();
        }
    }
}