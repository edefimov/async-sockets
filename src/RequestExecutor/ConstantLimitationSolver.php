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

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class ConstantLimitationSolver
 */
class ConstantLimitationSolver implements LimitationSolverInterface, EventHandlerInterface
{
    /**
     * Limit of running requests
     *
     * @var int
     */
    private $limit;

    /**
     * Key with number of active requests in execution context
     *
     * @var string
     */
    private $key;

    /**
     * ConstantLimitationSolver constructor.
     *
     * @param int $limit Limit of running requests
     */
    public function __construct($limit)
    {
        $this->limit = $limit;
        $this->key   = uniqid(__CLASS__, true);
    }

    /** {@inheritdoc} */
    public function initialize(RequestExecutorInterface $executor, ExecutionContext $executionContext)
    {
        $executionContext->inNamespace($this->key)->set('active_requests', 0);
    }

    /** {@inheritdoc} */
    public function finalize(RequestExecutorInterface $executor, ExecutionContext $executionContext)
    {
        // empty body
    }

    /** {@inheritdoc} */
    public function decide(
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $executionContext,
        $totalSockets
    ) {
        $activeRequests = $executionContext->inNamespace($this->key)->get('active_requests');
        if ($activeRequests + 1 <= $this->limit) {
            return self::DECISION_OK;
        } else {
            return self::DECISION_PROCESS_SCHEDULED;
        }
    }

    /** {@inheritdoc} */
    public function invokeEvent(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        switch ($event->getType()) {
            case EventType::INITIALIZE:
                $this->onSocketRequestInitialize($context);
                break;
            case EventType::FINALIZE:
                $this->onSocketRequestFinalize($context);
                break;
        }
    }

    /**
     * Process socket initialize event
     *
     * @param ExecutionContext $executionContext Execution context
     *
     * @return void
     */
    private function onSocketRequestInitialize(ExecutionContext $executionContext)
    {
        $context = $executionContext->inNamespace($this->key);
        $context->set(
            'active_requests',
            $context->get('active_requests') + 1
        );
    }

    /**
     * Process request termination
     *
     * @param ExecutionContext $executionContext Execution context
     *
     * @return void
     */
    private function onSocketRequestFinalize(ExecutionContext $executionContext)
    {
        $context = $executionContext->inNamespace($this->key);
        $context->set(
            'active_requests',
            $context->get('active_requests') - 1
        );
    }
}
