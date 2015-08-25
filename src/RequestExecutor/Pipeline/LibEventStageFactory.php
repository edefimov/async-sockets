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

use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class LibEventStageFactory
 */
class LibEventStageFactory extends AbstractStageFactory
{
    /** {@inheritdoc} */
    public function createConnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        LimitationSolverInterface $limitationSolver
    ) {
        return new ConnectStage($executor, $caller, $limitationSolver);
    }
}
