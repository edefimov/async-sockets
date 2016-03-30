<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\RequestExecutor\LibEvent;

use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class LibEventLoopEmulator
 */
class LibEventLoopEmulator
{
    /**
     * Array of events
     *
     * @var array[]
     */
    private $events = [];

    /**
     * Should we break the loop?
     *
     * @var bool
     */
    private $breakLoop = false;

    /**
     * Before event handler for test classes
     *
     * @var callable
     */
    private $onBeforeEvent;

    /**
     * LibEventLoopEmulator constructor.
     */
    public function __construct()
    {
        $emptyFunction = function () {

        };
        PhpFunctionMocker::getPhpFunctionMocker('event_base_loop')->setCallable(
            [$this, 'eventBaseLoop']
        );
        PhpFunctionMocker::getPhpFunctionMocker('event_set')->setCallable(
            [$this, 'eventSet']
        );
        PhpFunctionMocker::getPhpFunctionMocker('event_new')->setCallable($emptyFunction);
        PhpFunctionMocker::getPhpFunctionMocker('event_add')->setCallable($emptyFunction);
        PhpFunctionMocker::getPhpFunctionMocker('event_del')->setCallable($emptyFunction);
        PhpFunctionMocker::getPhpFunctionMocker('event_free')->setCallable($emptyFunction);
        PhpFunctionMocker::getPhpFunctionMocker('event_base_set')->setCallable($emptyFunction);
        PhpFunctionMocker::getPhpFunctionMocker('event_base_loopbreak')->setCallable(
            function () {
                $this->breakLoop = true;
            }
        );
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        PhpFunctionMocker::getPhpFunctionMocker('event_base_loop')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_base_loopbreak')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_base_set')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_new')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_set')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_add')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_del')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('event_free')->restoreNativeHandler();
    }

    /**
     * Set before event handler
     *
     * @param callable|null $callable Callable: void callback(LibEventEmulatedEvent $event)
     *
     * @return void
     */
    public function onBeforeEvent($callable)
    {
        $this->onBeforeEvent = $callable;
    }

    /**
     * eventBaseLoop
     *
     * @return void
     */
    public function eventBaseLoop()
    {
        while (!$this->breakLoop && $event = array_pop($this->events)) {
            $obj = new LibEventEmulatedEvent($event['flags'], $event['arg']);

            if ($this->onBeforeEvent) {
                call_user_func_array($this->onBeforeEvent, [$obj]);
            }


            call_user_func_array(
                $event['callback'],
                [
                    $event['fd'],
                    $obj->getEventFlags(),
                    $event['arg']
                ]
            );
        }
    }

    /**
     * event_set emulator
     *
     * @param int      $event Event index
     * @param resource $resource File descriptor
     * @param int      $flags Event flags to listen
     * @param callable $callback Event callback
     * @param null     $arg Argument for callback
     *
     * @return void
     */
    public function eventSet($event, $resource, $flags, $callback, $arg = null)
    {
        $this->events[] = [
            'fd'       => $resource,
            'callback' => $callback,
            'flags'    => $flags,
            'arg'      => $arg,
        ];
    }
}
