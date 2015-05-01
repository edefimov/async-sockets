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

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Define AbstractEvent as the extension of Symfony event object, so if you have installed
 * symfony EventDispatcher component then AbstractEvent will be fully compatible with
 * EventDispatcher's event system
 */
if (class_exists('Symfony\Component\EventDispatcher\Event', true)) {
    /**
     * Class AbstractEvent
     */
    class AbstractEvent extends SymfonyEvent
    {

    }
} else {
    /**
     * Class AbstractEvent
     */
    class AbstractEvent
    {

    }
}
