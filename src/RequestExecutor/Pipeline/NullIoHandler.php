<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Operation\NullOperation;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class NullIoHandler
 */
class NullIoHandler implements IoHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(OperationInterface $operation)
    {
        return $operation instanceof NullOperation;
    }

    /**
     * @inheritDoc
     */
    public function handle(
        OperationInterface $operation,
        SocketInterface $socket,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    ) {
        if (!($operation instanceof NullOperation)) {
            throw new \LogicException(
                'Can not use ' . get_class($this) . ' for ' . get_class($operation) . ' operation'
            );
        }

        $meta  = $executor->socketBag()->getSocketMetaData($socket);
        $event = new IoEvent(
            $executor,
            $socket,
            $meta[ RequestExecutorInterface::META_USER_CONTEXT ],
            EventType::DATA_ARRIVED
        );
        try {
            $eventHandler->invokeEvent($event);

            return $event->getNextOperation();
        } catch (SocketException $e) {
            $exceptionEvent = new SocketExceptionEvent(
                $e,
                $executor,
                $socket,
                $meta[ RequestExecutorInterface::META_USER_CONTEXT ]
            );
            $eventHandler->invokeEvent($exceptionEvent);

            return null;
        }
    }
}
