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

use AsyncSockets\Event\Event;
use AsyncSockets\RequestExecutor\Pipeline\Pipeline;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class RequestExecutor
 */
class RequestExecutor implements RequestExecutorInterface
{
    /**
     * Flag whether we are executing request
     *
     * @var bool
     */
    private $isExecuting = false;

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
    private $Pipeline;

    /**
     * RequestExecutor constructor.
     */
    public function __construct()
    {
        $this->socketBag = new SocketBag($this);
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
        return $this->isExecuting;
    }

    /** {@inheritdoc} */
    public function executeRequest()
    {
        if ($this->isExecuting()) {
            throw new \LogicException('Request is already in progress');
        }

        $Pipeline = $this->getPipeline();
        $Pipeline->setLimitationDecider($this->decider);

        $this->isExecuting = true;

        $eventCaller = new EventCaller($this);
        if ($this->eventInvocationHandler) {
            $eventCaller->addHandler($this->eventInvocationHandler);
        }

        if ($this->decider instanceof EventHandlerInterface) {
            $eventCaller->addHandler($this->decider);
        }

        $eventCaller->addHandler($this->getPipeline());

        try {
            $this->getPipeline()->process($this, $this->socketBag, $eventCaller);
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

        $this->getPipeline()->stopRequest();
    }

    /** {@inheritdoc} */
    public function setLimitationDecider(LimitationDeciderInterface $decider = null)
    {
        if ($this->isExecuting()) {
            throw new \BadMethodCallException('Can not change limitation decider during request processing');
        }

        $this->decider = $decider;
    }

    /**
     * Get active Pipeline
     *
     * @return Pipeline
     */
    private function getPipeline()
    {
        if (!$this->Pipeline) {
            $this->Pipeline = new Pipeline($this->socketBag);
        }

        return $this->Pipeline;
    }
}
