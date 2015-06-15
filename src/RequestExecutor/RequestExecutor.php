<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\RequestExecutor;

use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\Pipeline;
use AsyncSockets\RequestExecutor\Pipeline\PipelineFactory;

/**
 * Class RequestExecutor
 */
class RequestExecutor implements RequestExecutorInterface
{
    /**
     * Decider for request limitation
     *
     * @var LimitationSolverInterface
     */
    private $solver;

    /**
     * EventHandlerInterface
     *
     * @var EventHandlerInterface
     */
    private $eventHandler;

    /**
     * Socket bag
     *
     * @var SocketBag
     */
    private $socketBag;

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
     */
    public function __construct(PipelineFactory $pipelineFactory)
    {
        $this->socketBag       = new SocketBag($this);
        $this->pipelineFactory = $pipelineFactory;
    }

    /** {@inheritdoc} */
    public function socketBag()
    {
        return $this->socketBag;
    }

    /** {@inheritdoc} */
    public function withEventHandler(EventHandlerInterface $handler = null)
    {
        $this->eventHandler = $handler;
    }

    /** {@inheritdoc} */
    public function isExecuting()
    {
        return $this->pipeline !== null;
    }

    /** {@inheritdoc} */
    public function executeRequest()
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Request is already in progress');
        }

        $eventCaller   = new EventCaller($this);
        $this->solver = $this->solver ?: new NoLimitationSolver();
        if ($this->eventHandler) {
            $eventCaller->addHandler($this->eventHandler);
        }

        if ($this->solver instanceof EventHandlerInterface) {
            $eventCaller->addHandler($this->solver);
        }

        $this->pipeline  = $this->pipelineFactory->createPipeline($this, $eventCaller, $this->solver);

        $eventCaller->addHandler($this->pipeline);

        try {
            $this->solver->initialize($this);
            $this->pipeline->process($this->socketBag, $eventCaller);
            $this->solver->finalize($this);
        } catch (\Exception $e) {
            $this->solver->finalize($this);
            $this->pipeline = null;
            throw $e;
        }

        $this->pipeline = null;
    }

    /** {@inheritdoc} */
    public function stopRequest()
    {
        if (!$this->isExecuting()) {
            throw new \BadMethodCallException('Can not stop inactive request');
        }

        $this->pipeline->stopRequest();
    }

    /** {@inheritdoc} */
    public function withLimitationSolver(LimitationSolverInterface $solver)
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Can not change limitation solver during request processing');
        }

        $this->solver = $solver;
    }
}
