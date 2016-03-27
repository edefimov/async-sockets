<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\DataAlertEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Exception\SocketException;
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
