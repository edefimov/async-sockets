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
 * Class EventMultiHandler
 */
class EventMultiHandler implements EventHandlerInterface
{
    /**
     * List of handlers
     *
     * @var EventHandlerInterface[]
     */
    private $handlers = [];

    /**
     * EventMultiHandler constructor.
     *
     * @param EventHandlerInterface[] $handlers List of handlers
     */
    public function __construct(array $handlers = [])
    {
        $this->handlers = [];
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        foreach ($this->handlers as $handler) {
            $handler->invokeEvent($event);
        }
    }

    /**
     * Add handler to list
     *
     * @param EventHandlerInterface $handler Handler to add
     *
     * @return void
     */
    public function addHandler(EventHandlerInterface $handler)
    {
        $key = $this->getHandlerKey($handler);
        $this->handlers[$key] = $handler;
    }

    /**
     * Remove handler from list
     *
     * @param EventHandlerInterface $handler Handler to remove
     *
     * @return void
     */
    public function removeHandler(EventHandlerInterface $handler)
    {
        $key = $this->getHandlerKey($handler);
        if (isset($this->handlers[$key])) {
            unset($this->handlers[$key]);
        }
    }

    /**
     * Return key for given handler
     *
     * @param EventHandlerInterface $handler Handler to get key for
     *
     * @return string
     */
    private function getHandlerKey(EventHandlerInterface $handler)
    {
        return spl_object_hash($handler);
    }
}
