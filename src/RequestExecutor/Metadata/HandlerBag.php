<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Metadata;
 
/**
 * Class HandlerBag
 */
class HandlerBag
{
    /**
     * List of callables in this bag indexed by event name
     *
     * @var array
     */
    private $handlers = [];

    /**
     * Add handler into this bag
     *
     * @param array $events Events to handle: [ "eventName" => [callable, callable, ...], ... ]
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
     * @param array $events Events to remove: [ "eventName" => [callable, callable, ...], ... ]
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
     * Return array of callables for given event name
     *
     * @param string $eventName Event name to get handlers for
     *
     * @return callable[]
     */
    public function getHandlersFor($eventName)
    {
        return isset($this->handlers[$eventName]) ? $this->handlers[$eventName] : [];
    }
}
