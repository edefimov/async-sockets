<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class EventHandlerInterfaceTest
 */
abstract class EventHandlerInterfaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Xxecutor
     *
     * @var RequestExecutorInterface
     */
    protected $executor;

    /**
     * Socket
     *
     * @var SocketInterface
     */
    protected $socket;

    /**
     * Execution context
     *
     * @var ExecutionContext
     */
    protected $executionContext;

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

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->executionContext = new ExecutionContext();

        $this->executor = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                               ->getMockForAbstractClass();

        $this->socket = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
                             ->getMockForAbstractClass();
    }
}
