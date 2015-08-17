<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Socket\SocketInterface;

/**
 * Interface IoHandlerInterface
 */
interface IoHandlerInterface
{
    /**
     * Check whether this handler supports given operation
     *
     * @param OperationInterface $operation Operation to test
     *
     * @return bool
     */
    public function supports(OperationInterface $operation);

    /**
     * Process given operation
     *
     * @param OperationInterface       $operation Operation to process
     * @param SocketInterface          $socket Socket to process operation for
     * @param RequestExecutorInterface $executor Executor, processing operation
     * @param EventHandlerInterface    $eventHandler Event handler for this operation
     *
     * @return OperationInterface|null Next operation to pass in socket. Return null,
     *      if next operation is not required. Return $operation parameter, if operation is not completed yet
     */
    public function handle(
        OperationInterface $operation,
        SocketInterface $socket,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    );
}
