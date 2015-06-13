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

use AsyncSockets\RequestExecutor\LimitationDeciderInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Class PipelineFactory
 */
class PipelineFactory
{
    /**
     * Create Pipeline
     *
     * @param RequestExecutorInterface   $executor Request executor
     * @param EventCaller                $eventCaller Event caller
     * @param LimitationDeciderInterface $limitationDecider Limitation decider
     *
     * @return Pipeline
     */
    public function createPipeline(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        LimitationDeciderInterface $limitationDecider
    ) {
        $selector        = $this->createSelector();
        $disconnectStage = $this->createDisconnectStage($executor, $eventCaller, $selector);
        return new Pipeline(
            new ConnectStage($executor, $eventCaller, $limitationDecider),
            [
                new GetExcludedOperationsStage(
                    $executor,
                    $eventCaller,
                    [
                        new SelectStage($executor, $eventCaller, $selector),
                        new IoStage($executor, $eventCaller),
                        $disconnectStage
                    ]
                ),
                new TimeoutStage($executor, $eventCaller),
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

    /**
     * Create DisconnectStage
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $eventCaller Event caller
     * @param AsyncSelector            $selector Selector object
     *
     * @return DisconnectStage
     */
    protected function createDisconnectStage(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        AsyncSelector $selector
    ) {
        return new DisconnectStage($executor, $eventCaller, $selector);
    }
}
