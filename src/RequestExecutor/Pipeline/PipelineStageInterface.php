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

/**
 * Interface PipelineStageInterface
 */
interface PipelineStageInterface
{
    /**
     * Process pipeline stage
     *
     * @param RequestDescriptor[] $requestDescriptors List of requestDescriptors to process
     *
     * @return RequestDescriptor[] List of processed requestDescriptors
     */
    public function processStage(array $requestDescriptors);
}
