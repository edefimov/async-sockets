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

use AsyncSockets\Operation\NullOperation;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class NullIoHandler
 */
class NullIoHandler extends AbstractOobHandler
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
    protected function handleOperation(
        OperationInterface $operation,
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    ) {
        // empty body
    }

    /**
     * @inheritDoc
     */
    protected function getHandlerType()
    {
        return RequestDescriptor::RDS_READ;
    }

}
