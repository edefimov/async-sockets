<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
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
     * Additional arguments to pass into callback
     *
     * @var array
     */
    private $arguments;

    /**
     * DelayedOperation constructor.
     *
     * @param OperationInterface $origin Original operation which should be delayed
     * @param callable           $callable Function to check whether operation is pending:
     *   bool function(SocketInterface $socket, RequestExecutorInterface $executor, ...$arguments)
     * @param array              $arguments Additional arguments to pass into callback
     */
    public function __construct(OperationInterface $origin, callable $callable, array $arguments = [])
    {
        $this->origin    = $origin;
        $this->callable  = $callable;
        $this->arguments = $arguments;
    }

    /** {@inheritdoc} */
    public function getTypes()
    {
        return $this->origin->getTypes();
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

    /**
     * Return additional arguments to pass into callback
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
