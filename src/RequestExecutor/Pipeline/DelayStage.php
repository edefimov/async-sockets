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

use AsyncSockets\Operation\DelayedOperation;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;

/**
 * Class DelayStage
 */
class DelayStage extends AbstractStage
{
    /** {@inheritdoc} */
    public function processStage(array $requestDescriptors)
    {
        $result = [];

        /** @var RequestDescriptor[] $requestDescriptors */
        foreach ($requestDescriptors as $requestDescriptor) {
            $operation = $requestDescriptor->getOperation();
            if ($operation instanceof DelayedOperation) {
                if ($this->checkDelayIsFinished($requestDescriptor)) {
                    $requestDescriptor->setOperation($operation->getOriginalOperation());
                    $result[] = $requestDescriptor;
                }
            } else {
                $result[] = $requestDescriptor;
            }
        }

        return $result;
    }

    /**
     * Check whether socket waiting is finished
     *
     * @param RequestDescriptor $descriptor Request descriptor to test
     *
     * @return bool True if delay is complete, false otherwise
     */
    private function checkDelayIsFinished(RequestDescriptor $descriptor)
    {
        /** @var DelayedOperation $socketOperation */
        $socketOperation = $descriptor->getOperation();
        $arguments       = $socketOperation->getArguments();
        array_unshift($arguments, $descriptor->getSocket(), $this->executor);

        return !call_user_func_array(
            $socketOperation->getCallable(),
            $arguments
        );
    }
}
