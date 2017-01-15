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
use AsyncSockets\Event\Event;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\StopRequestExecuteException;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;

/**
 * Class AbstractRequestExecutor
 */
abstract class AbstractRequestExecutor implements RequestExecutorInterface, EventHandlerInterface
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
     * @var EventHandlerInterface[]
     */
    protected $eventHandlers = [];

    /**
     * Socket bag
     *
     * @var SocketBag
     */
    protected $socketBag;

    /**
     * Flag, indicating stopping request
     *
     * @var bool
     */
    private $isRequestStopped = false;

    /**
     * Flag, indicating stopping request is in progress
     *
     * @var bool
     */
    private $isRequestStopInProgress = false;

    /**
     * Flag whether request is executing
     *
     * @var bool
     */
    private $isExecuting = false;

    /**
     * AbstractRequestExecutor constructor.
     *
     * @param Configuration $configuration Configuration for executor
     */
    public function __construct(Configuration $configuration)
    {
        $this->socketBag = new SocketBag($this, $configuration);
    }

    /** {@inheritdoc} */
    public function socketBag()
    {
        return $this->socketBag;
    }

    /** {@inheritdoc} */
    public function withEventHandler(EventHandlerInterface $handler)
    {
        $key = spl_object_hash($handler);
        $this->eventHandlers[$key] = $handler;
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

        $this->isRequestStopped = false;
        $this->solver           = $this->solver ?: new NoLimitationSolver();

        $this->isExecuting = true;

        $eventCaller = new EventCaller($this);
        try {
            foreach ($this->eventHandlers as $handler) {
                $eventCaller->addHandler($handler);
            }

            if ($this->solver instanceof EventHandlerInterface) {
                $eventCaller->addHandler($this->solver);
            }

            $this->initializeRequest($eventCaller);

            $eventCaller->addHandler($this);

            $this->solver->initialize($this);
            $this->doExecuteRequest($eventCaller);
            $this->solver->finalize($this);

            $this->terminateRequest();
        } catch (StopRequestExecuteException $e) {
            $this->isRequestStopInProgress = true;
            $this->disconnectItems($this->socketBag->getItems());
        } catch (SocketException $e) {
            foreach ($this->socketBag->getItems() as $item) {
                $eventCaller->setCurrentOperation($item);
                $eventCaller->callExceptionSubscribers($item, $e);
            }

            $this->disconnectItems($this->socketBag->getItems());
        } catch (\Exception $e) {
            $this->isExecuting = false;
            $this->emergencyShutdown();
            $this->solver->finalize($this);
            $this->terminateRequest();
            throw $e;
        }

        $this->solver->finalize($this);
        $this->terminateRequest();
        $this->isExecuting = false;
    }

    /** {@inheritdoc} */
    public function stopRequest()
    {
        if (!$this->isExecuting()) {
            throw new \BadMethodCallException('Can not stop inactive request');
        }

        $this->isRequestStopped = true;
    }

    /**
     * Prepare executor for request
     *
     * @param EventCaller $eventCaller Event caller
     *
     * @return void
     */
    protected function initializeRequest(EventCaller $eventCaller)
    {
        // empty body
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
     * Terminate request in executor
     *
     * @return void
     */
    protected function terminateRequest()
    {
        // empty body
    }

    /**
     * Disconnect given sockets
     *
     * @param RequestDescriptor[] $items Sockets' operations to disconnect
     *
     * @return mixed
     */
    abstract protected function disconnectItems(array $items);

    /**
     * Shutdown all sockets in case of unhandled exception
     *
     * @return void
     */
    private function emergencyShutdown()
    {
        foreach ($this->socketBag->getItems() as $item) {
            try {
                $item->getSocket()->close();
            } catch (\Exception $e) {
                // nothing required
            }

            $item->setMetadata(self::META_REQUEST_COMPLETE, true);
        }
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        if ($this->isRequestStopped && !$this->isRequestStopInProgress) {
            $this->isRequestStopInProgress = true;
            throw new StopRequestExecuteException();
        }
    }
}
