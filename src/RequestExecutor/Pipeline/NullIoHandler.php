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
        // empty body
    }
}
