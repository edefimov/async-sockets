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

use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ConnectStageReturningAllActiveSockets
 */
class ConnectStageReturningAllActiveSockets extends ConnectStage
{
    /** {@inheritdoc} */
    public function processStage(array $requestDescriptors)
    {
        parent::processStage($requestDescriptors);
        return $this->getActiveOperations($requestDescriptors);
    }

    /**
     * Return array of keys for socket waiting for processing
     *
     * @param RequestDescriptor[] $requestDescriptors List of all requestDescriptors
     *
     * @return RequestDescriptor[]
     */
    private function getActiveOperations(array $requestDescriptors)
    {
        $result = [];
        foreach ($requestDescriptors as $key => $descriptor) {
            if ($this->isDescriptorActive($descriptor)) {
                $result[$key] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * Check whether given descriptor is active
     *
     * @param RequestDescriptor $descriptor
     *
     * @return bool
     */
    private function isDescriptorActive(RequestDescriptor $descriptor)
    {
        $meta = $descriptor->getMetadata();
        return !$meta[ RequestExecutorInterface::META_REQUEST_COMPLETE ] &&
                    $descriptor->isRunning() &&
                    !$descriptor->isPostponed();
    }
}
