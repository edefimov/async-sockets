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

use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Interface StageFactoryInterface
 */
interface StageFactoryInterface
{
    /**
     * Create delay stage handler
     *
     * @param RequestExecutorInterface  $executor Request executor
     * @param EventCaller               $caller Event caller
     *
     * @return PipelineStageInterface
     */
    public function createDelayStage(
        RequestExecutorInterface $executor,
        EventCaller $caller
    );

    /**
     * Create connect stage handler
     *
     * @param RequestExecutorInterface  $executor Request executor
     * @param EventCaller               $caller Event caller
     * @param LimitationSolverInterface $limitationSolver Limitation solver
     *
     * @return PipelineStageInterface
     */
    public function createConnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        LimitationSolverInterface $limitationSolver
    );

    /**
     * Create I/O stage
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $caller Event caller
     *
     * @return PipelineStageInterface
     */
    public function createIoStage(RequestExecutorInterface $executor, EventCaller $caller);

    /**
     * createDisconnectStage
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $caller Event caller
     * @param AsyncSelector            $selector Selector object
     *
     * @return PipelineStageInterface
     */
    public function createDisconnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        AsyncSelector $selector = null
    );
}
