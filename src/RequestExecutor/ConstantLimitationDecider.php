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

use AsyncSockets\Event\EventType;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class ConstantLimitationDecider
 */
class ConstantLimitationDecider implements LimitationDecider
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
     * ConstantLimitationDecider constructor.
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
        $executor->addHandler(
            [
                EventType::INITIALIZE => [$this, 'onSocketRequestInitialize'],
                EventType::FINALIZE   => [$this, 'onSocketRequestFinalize'],
            ]
        );
    }

    /** {@inheritdoc} */
    public function finalize(RequestExecutorInterface $executor)
    {
        $executor->removeHandler(
            [
                EventType::INITIALIZE => [$this, 'onSocketRequestInitialize'],
                EventType::FINALIZE   => [$this, 'onSocketRequestFinalize'],
            ]
        );
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

    /**
     * Process socket initialize event
     *
     * @return void
     */
    public function onSocketRequestInitialize()
    {
        $this->activeRequests += 1;
    }

    /**
     * Process request termination
     *
     * @return void
     */
    public function onSocketRequestFinalize()
    {
        $this->activeRequests -= 1;
    }
}
