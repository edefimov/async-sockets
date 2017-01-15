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
     * Number of active requests now
     *
     * @var int
     */
    private $activeRequests;

    /**
     * ConstantLimitationSolver constructor.
     *
     * @param int $limit Limit of running requests
     */
    public function __construct($limit)
    {
        $this->limit = $limit;
    }

    /** {@inheritdoc} */
    public function initialize(RequestExecutorInterface $executor)
    {
        $this->activeRequests = 0;
    }

    /** {@inheritdoc} */
    public function finalize(RequestExecutorInterface $executor)
    {
        // empty body
    }

    /** {@inheritdoc} */
    public function decide(RequestExecutorInterface $executor, SocketInterface $socket, $totalSockets)
    {
        if ($this->activeRequests + 1 <= $this->limit) {
            return self::DECISION_OK;
        } else {
            return self::DECISION_PROCESS_SCHEDULED;
        }
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        switch ($event->getType()) {
            case EventType::INITIALIZE:
                $this->onSocketRequestInitialize();
                break;
            case EventType::FINALIZE:
                $this->onSocketRequestFinalize();
                break;
        }
    }


    /**
     * Process socket initialize event
     *
     * @return void
     */
    private function onSocketRequestInitialize()
    {
        $this->activeRequests += 1;
    }

    /**
     * Process request termination
     *
     * @return void
     */
    private function onSocketRequestFinalize()
    {
        $this->activeRequests -= 1;
    }
}
