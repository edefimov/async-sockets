<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\Pipeline;
use AsyncSockets\RequestExecutor\Pipeline\PipelineFactory;

/**
 * Class RequestExecutor
 */
class NativeRequestExecutor extends AbstractRequestExecutor
{
    /**
     * Pipeline
     *
     * @var Pipeline
     */
    private $pipeline;

    /**
     * PipelineFactory
     *
     * @var PipelineFactory
     */
    private $pipelineFactory;

    /**
     * RequestExecutor constructor.
     *
     * @param PipelineFactory $pipelineFactory Pipeline factory
     * @param Configuration   $configuration Configuration for executor
     */
    public function __construct(PipelineFactory $pipelineFactory, Configuration $configuration)
    {
        parent::__construct($configuration);
        $this->pipelineFactory = $pipelineFactory;
    }

    /** {@inheritdoc} */
    protected function initializeRequest(EventCaller $eventCaller)
    {
        parent::initializeRequest($eventCaller);
        $this->pipeline = $this->pipelineFactory->createPipeline($this, $eventCaller, $this->solver);
    }

    /** {@inheritdoc} */
    protected function terminateRequest()
    {
        parent::terminateRequest();
        $this->pipeline = null;
    }

    /** {@inheritdoc} */
    protected function doExecuteRequest(EventCaller $eventCaller)
    {
        $this->pipeline->process($this->socketBag);
    }

    /** {@inheritdoc} */
    protected function disconnectItems(array $items)
    {
        $this->pipeline->disconnectSockets($items);
    }
}
