<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Operation\NullOperation;
use AsyncSockets\RequestExecutor\Pipeline\NullIoHandler;

/**
 * Class NullIoHandlerTest
 */
class NullIoHandlerTest extends AbstractIoHandlerTest
{
    /**
     * @inheritDoc
     */
    protected function createIoHandlerInterface()
    {
        return new NullIoHandler();
    }

    /**
     * testHandleOperation
     *
     * @return void
     */
    public function testHandleOperation()
    {
        $this->mockEventHandler->expects(self::never())
            ->method('invokeEvent');
        
        $result = $this->handler->handle(
            new NullOperation(),
            $this->socket,
            $this->executor,
            $this->mockEventHandler
        );

        self::assertNull($result, 'NullIoHandler must not return anything from handler');
    }

    /**
     * testSupports
     *
     * @return void
     */
    public function testSupports()
    {
        self::assertTrue(
            $this->handler->supports(new NullOperation()),
            'Must support NullOperation'
        );
    }
}
