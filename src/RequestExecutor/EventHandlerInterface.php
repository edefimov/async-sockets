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
use AsyncSockets\Socket\SocketInterface;

/**
 * Interface EventHandlerInterface
 */
interface EventHandlerInterface
{
    /**
     * Invokes on each event in RequestExecutor
     *
     * @param Event                    $event    Event object
     * @param RequestExecutorInterface $executor Request executor fired an event
     * @param SocketInterface          $socket   Socket connected with event
     * @param ExecutionContext         $context  Global data context
     *
     * @return void
     */
    public function invokeEvent(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    );
}
