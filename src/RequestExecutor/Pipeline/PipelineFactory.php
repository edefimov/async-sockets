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
 * Class PipelineFactory
 */
class PipelineFactory
{
    /**
     * Stage factory
     *
     * @var StageFactoryInterface
     */
    private $stageFactory;

    /**
     * PipelineFactory constructor.
     *
     * @param StageFactoryInterface $stageFactory Stage factory
     */
    public function __construct(StageFactoryInterface $stageFactory)
    {
        $this->stageFactory = $stageFactory;
    }

    /**
     * Create Pipeline
     *
     * @param RequestExecutorInterface  $executor          Request executor
     * @param ExecutionContext          $executionContext  Execution context
     * @param EventCaller               $eventCaller       Event caller
     * @param LimitationSolverInterface $limitationDecider Limitation solver
     *
     * @return Pipeline
     */
    public function createPipeline(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $eventCaller,
        LimitationSolverInterface $limitationDecider
    ) {
        $selector        = $this->createSelector();
        $disconnectStage = $this->stageFactory->createDisconnectStage($executor, $executionContext, $eventCaller, $selector);

        return new Pipeline(
            $this->stageFactory->createConnectStage($executor, $executionContext, $eventCaller, $limitationDecider),
            [
                new ExcludedOperationsStage(
                    $executor,
                    $eventCaller,
                    $executionContext,
                    [
                        $this->stageFactory->createDelayStage($executor, $executionContext, $eventCaller),
                        new SelectStage($executor, $eventCaller, $executionContext, $selector),
                        $this->stageFactory->createIoStage($executor, $executionContext, $eventCaller),
                        $disconnectStage
                    ]
                ),
                new TimeoutStage($executor, $eventCaller, $executionContext),
                $disconnectStage
            ],
            $disconnectStage
        );
    }

    /**
     * Create AsyncSelector
     *
     * @return AsyncSelector
     */
    protected function createSelector()
    {
        return new AsyncSelector();
    }
}
