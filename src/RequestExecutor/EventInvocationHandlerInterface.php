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

/**
 * Interface EventInvocationHandlerInterface
 */
interface EventInvocationHandlerInterface
{
    /**
     * Invokes on each event in RequestExecutor
     *
     * @param Event $event Event object
     *
     * @return void
     */
    public function invokeEvent(Event $event);
}
