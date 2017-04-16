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
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class DelegatingIoHandler
 */
class DelegatingIoHandler implements IoHandlerInterface
{
    /**
     * List of nested handlers
     *
     * @var IoHandlerInterface[]
     */
    private $handlers;

    /**
     * DelegatingIoHandler constructor.
     *
     * @param IoHandlerInterface[] $handlers List of nested handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * @inheritDoc
     */
    public function supports(OperationInterface $operation)
    {
        return $this->getHandler($operation) !== null;
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
        $handler = $this->getHandler($operation);
        return $handler ?
            $handler->handle($operation, $descriptor, $executor, $eventHandler, $executionContext) :
            null;
    }

    /**
     * Return handler for given operation if there is any
     *
     * @param OperationInterface $operation Operation to get handler for
     *
     * @return IoHandlerInterface|null
     */
    private function getHandler(OperationInterface $operation)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($operation)) {
                return $handler;
            }
        }

        return null;
    }
}
