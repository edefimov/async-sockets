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

use AsyncSockets\RequestExecutor\ExecutionContext;
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
     * @param RequestExecutorInterface $executor         Request executor
     * @param ExecutionContext         $executionContext Execution context
     * @param EventCaller              $caller           Event caller
     *
     * @return PipelineStageInterface
     */
    public function createDelayStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller
    );

    /**
     * Create connect stage handler
     *
     * @param RequestExecutorInterface  $executor         Request executor
     * @param ExecutionContext          $executionContext Execution context
     * @param EventCaller               $caller           Event caller
     * @param LimitationSolverInterface $limitationSolver Limitation solver
     *
     * @return PipelineStageInterface
     */
    public function createConnectStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller,
        LimitationSolverInterface $limitationSolver
    );

    /**
     * Create I/O stage
     *
     * @param RequestExecutorInterface $executor         Request executor
     * @param ExecutionContext         $executionContext Execution context
     * @param EventCaller              $caller           Event caller
     *
     * @return PipelineStageInterface
     */
    public function createIoStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller
    );

    /**
     * Creates disconnect stage
     *
     * @param RequestExecutorInterface $executor         Request executor
     * @param ExecutionContext         $executionContext Execution context
     * @param EventCaller              $caller           Event caller
     * @param AsyncSelector            $selector         Selector object
     *
     * @return PipelineStageInterface
     */
    public function createDisconnectStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller,
        AsyncSelector $selector = null
    );
}
