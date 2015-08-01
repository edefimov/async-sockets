<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\LibEventRequestExecutor;
use Tests\Application\Mock\PhpFunctionMocker;
use Tests\AsyncSockets\RequestExecutor\LibEvent\LibEventEmulatedEvent;
use Tests\AsyncSockets\RequestExecutor\LibEvent\LibEventLoopEmulator;

/**
 * Class LibEventRequestExecutorTest
 */
class LibEventRequestExecutorTest extends AbstractRequestExecutorTest
{
    /**
     * LibEventLoopEmulator
     *
     * @var LibEventLoopEmulator
     */
    private $emulator;

    /** {@inheritdoc} */
    protected function createRequestExecutor()
    {
        return new LibEventRequestExecutor();
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->emulator = new LibEventLoopEmulator();
    }

    /**
     * prepareForTestTimeoutOnConnect
     *
     * @return void
     */
    public function prepareForTestTimeoutOnConnect()
    {
        $this->emulator->onBeforeEvent(function (LibEventEmulatedEvent $event) {
            $event->setEventFlags(EV_TIMEOUT);
        });
    }

    /**
     * prepareForTestTimeoutOnIo
     *
     * @return void
     */
    public function prepareForTestTimeoutOnIo()
    {
        $this->emulator->onBeforeEvent(function(LibEventEmulatedEvent $event) use (&$ioCount) {
            $event->setEventFlags(EV_TIMEOUT);
        });
    }

    /**
     * prepareForTestThrowsNonSocketExceptionInEvent
     *
     * @param string $eventType Event type to throw exception in
     *
     * @return void
     */
    protected function prepareForTestThrowsNonSocketExceptionInEvent($eventType)
    {
        if ($eventType === EventType::TIMEOUT) {
            $this->emulator->onBeforeEvent(function(LibEventEmulatedEvent $event) use ($eventType) {
                $event->setEventFlags(EV_TIMEOUT);
            });
        }
    }

    /**
     * prepareForTestThrowingSocketExceptionsInEvent
     *
     * @param string $eventType Event type to throw exception in
     *
     * @return void
     */
    protected function prepareForTestThrowingSocketExceptionsInEvent($eventType)
    {
        if ($eventType === EventType::TIMEOUT) {
            $this->emulator->onBeforeEvent(function(LibEventEmulatedEvent $event) use ($eventType) {
                $event->setEventFlags(EV_TIMEOUT);
            });
        }
    }
}
