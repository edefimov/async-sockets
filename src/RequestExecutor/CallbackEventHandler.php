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

/**
 * Class CallbackEventHandler
 */
class CallbackEventHandler implements \Countable, EventHandlerInterface
{
    /**
     * List of callables in this bag indexed by event name
     *
     * @var array
     */
    private $handlers = [];

    /**
     * CallbackEventHandler constructor.
     *
     * @param array $events Events to handle: [ "eventName" => callable|callable[], ... ]
     */
    public function __construct(array $events = [])
    {
        $this->handlers = [];
        $this->addHandler($events);
    }


    /**
     * Add handler into this bag
     *
     * @param array $events Events to handle: [ "eventName" => callable|callable[], ... ]
     *
     * @return void
     */
    public function addHandler(array $events)
    {
        foreach ($events as $eventName => $subscriber) {
            $this->handlers[$eventName] = array_merge(
                isset($this->handlers[$eventName]) ? $this->handlers[$eventName] : [],
                is_callable($subscriber) ? [$subscriber] : $subscriber
            );
        }
    }

    /**
     * Remove specified handlers from this bag
     *
     * @param array $events Events to remove: [ "eventName" => callable|callable[], ... ]
     *
     * @return void
     */
    public function removeHandler(array $events)
    {
        foreach ($events as $eventName => $subscribers) {
            if (!isset($this->handlers[$eventName])) {
                continue;
            }

            $subscribers = is_callable($subscribers) ? [$subscribers] : $subscribers;
            foreach ($subscribers as $subscriber) {
                $key = array_search($subscriber, $this->handlers[$eventName], true);
                if ($key !== false) {
                    unset($this->handlers[$eventName][$key]);
                }
            }
        }
    }

    /**
     * Remove all handlers from bag
     *
     * @return void
     */
    public function removeAll()
    {
        $this->handlers = [];
    }

    /**
     * Remove all handlers for given event name
     *
     * @param string $eventName Event name to remove handlers
     *
     * @return void
     */
    public function removeForEvent($eventName)
    {
        if (isset($this->handlers[$eventName])) {
            unset($this->handlers[$eventName]);
        }
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        $eventName   = $event->getType();
        $subscribers = isset($this->handlers[$eventName]) ? $this->handlers[$eventName] : [];
        foreach ($subscribers as $subscriber) {
            call_user_func($subscriber, $event);
        }
    }

    /** {@inheritdoc} */
    public function count()
    {
        return count($this->handlers);
    }
}
