<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Class AbstractStageFactory
 */
abstract class AbstractStageFactory implements StageFactoryInterface
{
    /** {@inheritdoc} */
    public function createIoStage(RequestExecutorInterface $executor, EventCaller $caller)
    {
        return new IoStage(
            $executor,
            $caller,
            [
                new ReadIoHandler(),
                new WriteIoHandler(),
                new SslHandshakeIoHandler()
            ]
        );
    }

    /** {@inheritdoc} */
    public function createDisconnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        AsyncSelector $selector = null
    ) {
        return new DisconnectStage($executor, $caller, $selector);
    }
}
