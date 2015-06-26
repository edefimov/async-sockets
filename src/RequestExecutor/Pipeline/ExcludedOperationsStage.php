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

use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ExcludedOperationsStage
 */
class ExcludedOperationsStage extends AbstractStage
{
    /**
     * Stages for processing successful operations
     *
     * @var PipelineStageInterface[]
     */
    private $stages = [];

    /**
     * AbstractStage constructor.
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $eventCaller Event caller
     * @param PipelineStageInterface[] $stages Stages for success way
     */
    public function __construct(RequestExecutorInterface $executor, EventCaller $eventCaller, array $stages)
    {
        parent::__construct($executor, $eventCaller);
        $this->stages = $stages;
    }

    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        $currentOperations = $operations;
        foreach ($this->stages as $stage) {
            $currentOperations = $stage->processStage($currentOperations);
        }

        foreach ($operations as $key => $item) {
            if (in_array($item, $currentOperations, true)) {
                unset($operations[$key]);
            }
        }

        return $operations;
    }
}
