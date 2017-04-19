<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadWriteOperation;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ReadWriteIoHandler
 */
class ReadWriteIoHandler implements IoHandlerInterface
{
    /**
     * Io handler
     *
     * @var IoHandlerInterface
     */
    private $handler;

    /**
     * Initialize I/O handler
     *
     * @param IoHandlerInterface $handler New value for Handler
     *
     * @return void
     */
    public function setHandler(IoHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    public function supports(OperationInterface $operation)
    {
        return $operation instanceof ReadWriteOperation;
    }

    /**
     * @inheritDoc
     */
    public function handle(
        OperationInterface $operation,
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    ) {
        /** @var ReadWriteOperation $operation */
        $sequence = $operation->isReadFirst() ?
            [$operation->getReadOperation(), $operation->getWriteOperation()] :
            [$operation->getWriteOperation(), $operation->getReadOperation()];
        $sequence = array_filter($sequence);

        foreach ($sequence as $op) {
            $this->handleOperation(
                $op,
                $operation,
                $descriptor,
                $executor,
                $eventHandler,
                $executionContext
            );
        }

        return $operation->getReadOperation() !== null || $operation->getWriteOperation() !== null ?
            $operation :
            null;
    }

    /**
     * Handle read operation
     *
     * @param OperationInterface       $nestedOperation  Nested I/O operation
     * @param ReadWriteOperation       $operation        Operation to process
     * @param RequestDescriptor        $descriptor       Request descriptor
     * @param RequestExecutorInterface $executor         Executor, processing operation
     * @param EventHandlerInterface    $eventHandler     Event handler for this operation
     * @param ExecutionContext         $executionContext Execution context
     *
     * @return void
     */
    private function handleOperation(
        OperationInterface $nestedOperation = null,
        ReadWriteOperation $operation,
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    ) {
        if (!$nestedOperation) {
            return;
        }

        $next = $this->handler->handle($nestedOperation, $descriptor, $executor, $eventHandler, $executionContext);
        if ($next !== $nestedOperation) {
            $operation->markCompleted($nestedOperation);
        }

        if (!$next) {
            return;
        }

        $operation->scheduleOperation($next);
    }
}
