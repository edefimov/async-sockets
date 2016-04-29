<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
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
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ReadIoHandler
 */
class ReadIoHandler extends AbstractOobHandler
{
    /** {@inheritdoc} */
    public function supports(OperationInterface $operation)
    {
        return $operation instanceof ReadOperation;
    }

    /** {@inheritdoc} */
    protected function handleOperation(
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    ) {
        $operation = $descriptor->getOperation();
        $socket    = $descriptor->getSocket();

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
                        $response->getRemoteAddress()
                    );

                    $eventHandler->invokeEvent($event);
                    return new ReadOperation();
                default:
                    $event = new ReadEvent(
                        $executor,
                        $socket,
                        $context,
                        $response,
                        false
                    );

                    $eventHandler->invokeEvent($event);
                    return $event->getNextOperation();
            }
        } catch (AcceptException $e) {
            return new ReadOperation();
        }
    }
}
