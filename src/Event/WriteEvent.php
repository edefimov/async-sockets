<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Event;

use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class WriteEvent
 */
class WriteEvent extends IoEvent
{
    /**
     * Operation to be used in I/O
     *
     * @var WriteOperation
     */
    private $operation;

    /**
     * Constructor
     *
     * @param WriteOperation           $operation Operation, which will be used in I/O
     * @param RequestExecutorInterface $executor Request executor object
     * @param SocketInterface          $socket   Socket for this request
     * @param mixed                    $context  Any optional user data for event
     */
    public function __construct(
        WriteOperation $operation,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        $context
    ) {
        parent::__construct($executor, $socket, $context, EventType::WRITE);
        $this->operation = $operation;
    }

    /**
     * Return operation object
     *
     * @return WriteOperation
     */
    public function getOperation()
    {
        return $this->operation;
    }
}
