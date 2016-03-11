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
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;

/**
 * Class DelayStage
 */
class DelayStage extends AbstractStage
{
    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        $result = [];

        /** @var OperationMetadata[] $operations */
        foreach ($operations as $operationMetadata) {
            $operation = $operationMetadata->getOperation();
            if ($operation instanceof DelayedOperation) {
                if ($this->checkDelayIsFinished($operationMetadata)) {
                    $operationMetadata->setOperation($operation->getOriginalOperation());
                    $result[] = $operationMetadata;
                }
            } else {
                $result[] = $operationMetadata;
            }
        }

        return $result;
    }

    /**
     * Check whether socket waiting is finished
     *
     * @param OperationMetadata $operation Operation metadata to test
     *
     * @return bool True if delay is complete, false otherwise
     */
    private function checkDelayIsFinished(OperationMetadata $operation)
    {
        /** @var DelayedOperation $socketOperation */
        $socketOperation = $operation->getOperation();
        $arguments       = $socketOperation->getArguments();
        array_unshift($arguments, $operation->getSocket(), $this->executor);

        return !call_user_func_array(
            $socketOperation->getCallable(),
            $arguments
        );
    }
}
