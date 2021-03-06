<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class NoLimitationSolver
 */
class NoLimitationSolver implements LimitationSolverInterface
{
    /** {@inheritdoc} */
    public function initialize(RequestExecutorInterface $executor, ExecutionContext $executionContext)
    {
        // empty body
    }

    /** {@inheritdoc} */
    public function finalize(RequestExecutorInterface $executor, ExecutionContext $executionContext)
    {
        // empty body
    }

    /** {@inheritdoc} */
    public function decide(
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $executionContext,
        $totalSockets
    ) {
        return self::DECISION_OK;
    }
}
