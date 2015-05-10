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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Interface EventDispatcherAwareInterface
 */
interface EventDispatcherAwareInterface
{
    /**
     * Sets EventDispatcher
     *
     * @param EventDispatcherInterface $eventDispatcher New value for EventDispatcher
     *
     * return void
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher = null);
}
