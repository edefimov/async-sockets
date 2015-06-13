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

/**
 * Interface PipelineStageInterface
 */
interface PipelineStageInterface
{
    /**
     * Process pipeline stage
     *
     * @param OperationMetadata[] $operations List of operations to process
     *
     * @return OperationMetadata[] List of processed operations
     */
    public function processStage(array $operations);
}
