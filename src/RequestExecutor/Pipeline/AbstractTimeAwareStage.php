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
 * Class AbstractTimeAwareStage
 */
abstract class AbstractTimeAwareStage extends AbstractStage
{
    /**
     * Return true, if connect time settings should be used for time operations, false otherwise
     *
     * @param OperationMetadata $operation Operation object
     *
     * @return bool
     */
    protected function hasConnected(OperationMetadata $operation)
    {
        $meta = $operation->getMetadata();
        return $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] !== null;
    }

    /**
     * Return timeout for current socket state
     *
     * @param OperationMetadata $operation Operation object
     *
     * @return double
     */
    protected function timeoutSetting(OperationMetadata $operation)
    {
        $meta = $operation->getMetadata();
        return !$this->hasConnected($operation) ?
            $meta[ RequestExecutorInterface::META_CONNECTION_TIMEOUT ] :
            $meta[ RequestExecutorInterface::META_IO_TIMEOUT ];
    }

    /**
     * Return time since last I/O for current socket state
     *
     * @param OperationMetadata $operation Operation object
     *
     * @return double|null
     */
    protected function timeSinceLastIo(OperationMetadata $operation)
    {
        $meta = $operation->getMetadata();
        return !$this->hasConnected($operation) ?
            $meta[ RequestExecutorInterface::META_CONNECTION_START_TIME ] :
            $meta[ RequestExecutorInterface::META_LAST_IO_START_TIME ];
    }

    /**
     * Set start or finish time in metadata of the socket
     *
     * @param OperationMetadata $operationMetadata Socket meta data
     * @param string            $key Metadata key to set
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function setSocketOperationTime(OperationMetadata $operationMetadata, $key)
    {
        $meta = $operationMetadata->getMetadata();
        switch ($key) {
            case RequestExecutorInterface::META_CONNECTION_START_TIME:
                $doSetValue = $meta[RequestExecutorInterface::META_CONNECTION_START_TIME] === null;
                break;

            case RequestExecutorInterface::META_CONNECTION_FINISH_TIME:
                $doSetValue = $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] === null;
                break;

            case RequestExecutorInterface::META_LAST_IO_START_TIME:
                $doSetValue = $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] !== null;
                break;

            default:
                throw new \InvalidArgumentException("Unexpected key parameter {$key} passed");
        }

        if ($doSetValue) {
            $operationMetadata->setMetadata($key, microtime(true));
        }
    }
}
