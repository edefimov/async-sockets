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

use AsyncSockets\Event\WriteEvent;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\Operation\InProgressWriteOperation;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class WriteIoHandler
 */
class WriteIoHandler implements IoHandlerInterface
{
    /** {@inheritdoc} */
    public function supports(OperationInterface $operation)
    {
        return ($operation instanceof WriteOperation) || ($operation instanceof InProgressWriteOperation);
    }

    /** {@inheritdoc} */
    public function handle(
        OperationInterface $operation,
        SocketInterface $socket,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    ) {
        if (!($operation instanceof WriteOperation)) {
            throw new \LogicException(
                'Can not use ' . get_class($this) . ' for ' . get_class($operation) . ' operation'
            );
        }

        $fireEvent = !($operation instanceof InProgressWriteOperation);

        if ($fireEvent) {
            $meta  = $executor->socketBag()->getSocketMetaData($socket);
            $event = new WriteEvent(
                $operation,
                $executor,
                $socket,
                $meta[ RequestExecutorInterface::META_USER_CONTEXT ]
            );
            $eventHandler->invokeEvent($event);
            $nextOperation = $event->getNextOperation();
        } else {
            $nextOperation = $operation;
        }

        return $this->writeDataToSocket($operation, $socket, $nextOperation);
    }

    /**
     * Perform data writing to socket and return suitable next socket operation
     *
     * @param WriteOperation     $operation Current write operation instance
     * @param SocketInterface    $socket Socket object
     * @param OperationInterface $nextOperation Desirable next operation
     *
     * @return OperationInterface Actual next operation
     */
    private function writeDataToSocket(
        WriteOperation $operation,
        SocketInterface $socket,
        OperationInterface $nextOperation = null
    ) {
        $result               = $nextOperation;
        $extractNextOperation = true;
        if ($operation->hasData()) {
            $data    = $operation->getData();
            $length  = strlen($data);
            $written = $socket->write($data);
            if ($length !== $written) {
                $extractNextOperation = false;

                $operation = $this->makeInProgressWriteOperation($operation, $nextOperation);
                $operation->setData(
                    substr($operation->getData(), $written)
                );

                $result = $operation;
            }
        }

        if ($extractNextOperation && ($operation instanceof InProgressWriteOperation)) {
            /** @var InProgressWriteOperation $operation */
            $result = $operation->getNextOperation();
        }

        return $result;
    }

    /**
     * Marks write operation as in progress
     *
     * @param WriteOperation     $operation Current operation object
     * @param OperationInterface $nextOperation Next planned operation
     *
     * @return InProgressWriteOperation Next operation object
     */
    private function makeInProgressWriteOperation(WriteOperation $operation, OperationInterface $nextOperation = null)
    {
        $result = $operation;
        if (!($result instanceof InProgressWriteOperation)) {
            $result = new InProgressWriteOperation($nextOperation, $operation->getData());
        }

        return $result;
    }
}
