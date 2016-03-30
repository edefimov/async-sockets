<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class EventHandlerFromSymfonyEventDispatcher
 */
class EventHandlerFromSymfonyEventDispatcher implements EventHandlerInterface
{
    /**
     * EventDispatcherInterface
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * EventHandlerFromSymfonyEventDispatcher constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher Symfony event dispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        $this->eventDispatcher->dispatch($event->getType(), $event);
    }
}
