<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Operation;

/**
 * Class DelayedOperation. Execution of this operation will be delayed until callable returns true.
 */
class DelayedOperation implements OperationInterface
{
    /**
     * Function to check whether operation is pending:
     *   bool function(SocketInterface $socket, RequestExecutorInterface $executor)
     *
     * @var callable
     */
    private $callable;

    /**
     * Original operation
     *
     * @var OperationInterface
     */
    private $origin;

    /**
     * DelayedOperation constructor.
     *
     * @param OperationInterface $origin Original operation which should be delayed
     * @param callable           $callable Function to check whether operation is pending:
     *   bool function(SocketInterface $socket, RequestExecutorInterface $executor)
     */
    public function __construct(OperationInterface $origin, callable $callable)
    {
        $this->origin   = $origin;
        $this->callable = $callable;
    }

    /** {@inheritdoc} */
    public function getType()
    {
        return $this->origin->getType();
    }

    /**
     * Returns function to invoke
     *
     * @return callable
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * Return original operation to run after delay
     *
     * @return OperationInterface
     */
    public function getOriginalOperation()
    {
        return $this->origin;
    }
}
