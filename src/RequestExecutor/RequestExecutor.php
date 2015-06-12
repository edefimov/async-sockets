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
     * @var LimitationDeciderInterface
     */
    private $decider;

    /**
     * EventHandlerInterface
     *
     * @var EventHandlerInterface
     */
    private $eventInvocationHandler;

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
    public function getSocketBag()
    {
        return $this->socketBag;
    }

    /** {@inheritdoc} */
    public function setEventInvocationHandler(EventHandlerInterface $handler = null)
    {
        $this->eventInvocationHandler = $handler;
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
            throw new \LogicException('Request is already in progress');
        }

        $eventCaller   = new EventCaller($this);
        $this->decider = $this->decider ?: new NoLimitationDecider();
        if ($this->eventInvocationHandler) {
            $eventCaller->addHandler($this->eventInvocationHandler);
        }

        if ($this->decider instanceof EventHandlerInterface) {
            $eventCaller->addHandler($this->decider);
        }

        $this->pipeline  = $this->pipelineFactory->createPipeline($this, $eventCaller, $this->decider);

        $eventCaller->addHandler($this->pipeline);

        try {
            $this->decider->initialize($this);
            $this->pipeline->process($this->socketBag, $eventCaller);
            $this->decider->finalize($this);
        } catch (\Exception $e) {
            $this->decider->finalize($this);
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
    public function setLimitationDecider(LimitationDeciderInterface $decider = null)
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Can not change limitation decider during request processing');
        }

        $this->decider = $decider;
    }
}
