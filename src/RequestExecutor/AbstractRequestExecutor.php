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

/**
 * Class AbstractRequestExecutor
 */
abstract class AbstractRequestExecutor implements RequestExecutorInterface
{
    /**
     * Decider for request limitation
     *
     * @var LimitationSolverInterface
     */
    protected $solver;

    /**
     * EventHandlerInterface
     *
     * @var EventHandlerInterface
     */
    protected $eventHandler;

    /**
     * Socket bag
     *
     * @var SocketBag
     */
    protected $socketBag;

    /**
     * Flag whether request is executing
     *
     * @var bool
     */
    private $isExecuting = false;

    /**
     * AbstractRequestExecutor constructor.
     */
    public function __construct()
    {
        $this->socketBag = new SocketBag($this);
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
    public function withLimitationSolver(LimitationSolverInterface $solver)
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Can not change limitation solver during request processing');
        }

        $this->solver = $solver;
    }

    /** {@inheritdoc} */
    public function isExecuting()
    {
        return $this->isExecuting;
    }

    /** {@inheritdoc} */
    public function executeRequest()
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Request is already in progress');
        }

        $this->solver = $this->solver ?: new NoLimitationSolver();

        $this->isExecuting = true;
        try {
            $eventCaller = new EventCaller($this);
            if ($this->eventHandler) {
                $eventCaller->addHandler($this->eventHandler);
            }

            if ($this->solver instanceof EventHandlerInterface) {
                $eventCaller->addHandler($this->solver);
            }

            $this->doExecuteRequest($eventCaller);
        } catch (\Exception $e) {
            $this->isExecuting = false;
            throw $e;
        }

        $this->isExecuting = false;
    }

    /** {@inheritdoc} */
    public function stopRequest()
    {
        if (!$this->isExecuting()) {
            throw new \BadMethodCallException('Can not stop inactive request');
        }

        $this->doStopRequest();
    }

    /**
     * Execute network request
     *
     * @param EventCaller $eventCaller Event caller object
     *
     * @return void
     */
    abstract protected function doExecuteRequest(EventCaller $eventCaller);

    /**
     * Stop request execution
     *
     * @return void
     */
    abstract protected function doStopRequest();
}
