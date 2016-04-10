<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

/**
 * Class AbstractEventTest
 */
class AbstractEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testEventInstanceOf
     *
     * @return void
     */
    public function testEventInstanceOf()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\Event')) {
            self::markTestSkipped('You must have symfony/event-dispatcher installed to pass this test');
        }

        $event = $this->getMockBuilder('AsyncSockets\Event\AbstractEvent')->getMock();
        self::assertInstanceOf(
            'Symfony\Component\EventDispatcher\Event',
            $event,
            'Symfony event dispatcher will fail to work properly'
        );
    }
}
