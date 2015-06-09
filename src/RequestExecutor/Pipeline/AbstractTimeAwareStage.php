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
     * Set start or finish time in metadata of the socket
     *
     * @param OperationMetadata $operationMetadata Socket meta data
     * @param string            $key Metadata key to set
     *
     * @return void
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
