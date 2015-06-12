<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Event;

/**
 * Define AbstractEvent as the alias for Symfony event object, so if you have installed
 * symfony EventDispatcher component then AbstractEvent will be fully compatible with
 * EventDispatcher's event system
 */
if (!class_alias('Symfony\Component\EventDispatcher\Event', 'AsyncSockets\Event\AbstractEvent', true)) {
    /**
     * Class AbstractEvent
     *
     * @noinspection EmptyClassInspection
     */
    abstract class AbstractEvent
    {

    }
}
