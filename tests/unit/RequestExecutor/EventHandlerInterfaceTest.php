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

use AsyncSockets\RequestExecutor\EventHandlerInterface;

/**
 * Class EventHandlerInterfaceTest
 */
abstract class EventHandlerInterfaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create mocked handler
     *
     * @return EventHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockedHandler()
    {
        return $this->getMockForAbstractClass(
            'AsyncSockets\RequestExecutor\EventHandlerInterface',
            [],
            '',
            true,
            true,
            true,
            ['invokeEvent']
        );
    }
}
