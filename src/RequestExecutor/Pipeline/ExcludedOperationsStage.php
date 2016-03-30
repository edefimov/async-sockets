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
     * Stages for processing successful descriptors
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
    public function processStage(array $requestDescriptors)
    {
        $currentOperations = $requestDescriptors;
        foreach ($this->stages as $stage) {
            $currentOperations = $stage->processStage($currentOperations);
        }

        foreach ($requestDescriptors as $key => $descriptor) {
            if (in_array($descriptor, $currentOperations, true)) {
                unset($requestDescriptors[ $key]);
            }
        }

        return $requestDescriptors;
    }
}
