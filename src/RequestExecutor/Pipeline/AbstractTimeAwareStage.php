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

use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class AbstractTimeAwareStage
 */
abstract class AbstractTimeAwareStage extends AbstractStage
{
    /**
     * Return true, if connect time settings should be used for time operations, false otherwise
     *
     * @param RequestDescriptor $operation Operation object
     *
     * @return bool
     */
    protected function hasConnected(RequestDescriptor $operation)
    {
        $meta = $operation->getMetadata();
        return $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] !== null;
    }

    /**
     * Return timeout for current socket state
     *
     * @param RequestDescriptor $operation Operation object
     *
     * @return double
     */
    protected function timeoutSetting(RequestDescriptor $operation)
    {
        $meta = $operation->getMetadata();
        return !$this->hasConnected($operation) ?
            $meta[ RequestExecutorInterface::META_CONNECTION_TIMEOUT ] :
            $meta[ RequestExecutorInterface::META_IO_TIMEOUT ];
    }

    /**
     * Return time since last I/O for current socket state
     *
     * @param RequestDescriptor $operation Operation object
     *
     * @return double|null
     */
    protected function timeSinceLastIo(RequestDescriptor $operation)
    {
        $meta = $operation->getMetadata();
        return !$this->hasConnected($operation) ?
            $meta[ RequestExecutorInterface::META_CONNECTION_START_TIME ] :
            $meta[ RequestExecutorInterface::META_LAST_IO_START_TIME ];
    }

    /**
     * Set start or finish time in metadata of the socket
     *
     * @param RequestDescriptor $requestDescriptor Socket meta data
     * @param string            $key Metadata key to set
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function setSocketOperationTime(RequestDescriptor $requestDescriptor, $key)
    {
        $meta  = $requestDescriptor->getMetadata();
        $table = [
            RequestExecutorInterface::META_CONNECTION_START_TIME =>
                $meta[ RequestExecutorInterface::META_CONNECTION_START_TIME ] === null,

            RequestExecutorInterface::META_CONNECTION_FINISH_TIME =>
                $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] === null,

            RequestExecutorInterface::META_LAST_IO_START_TIME =>
                $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] !== null
        ];

        if (isset($table[$key]) && $table[$key]) {
            $requestDescriptor->setMetadata($key, microtime(true));
        }
    }
}
