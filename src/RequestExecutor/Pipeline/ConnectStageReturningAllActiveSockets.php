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

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ConnectStageReturningAllActiveSockets
 */
class ConnectStageReturningAllActiveSockets extends ConnectStage
{
    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        parent::processStage($operations);
        return $this->getActiveOperations($operations);
    }

    /**
     * Return array of keys for socket waiting for processing
     *
     * @param OperationMetadata[] $operations List of all operations
     *
     * @return OperationMetadata[]
     */
    private function getActiveOperations(array $operations)
    {
        $result = [];
        foreach ($operations as $key => $item) {
            $meta     = $item->getMetadata();
            $isActive = !$meta[ RequestExecutorInterface::META_REQUEST_COMPLETE ] &&
                        $item->isRunning() &&
                        !$item->isPostponed();
            
            if ($isActive) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
