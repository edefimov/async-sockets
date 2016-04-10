<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Class BaseStageFactory
 */
class BaseStageFactory implements StageFactoryInterface
{
    /** {@inheritdoc} */
    public function createConnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        LimitationSolverInterface $limitationSolver
    ) {
        return new ConnectStageReturningAllActiveSockets($executor, $caller, $limitationSolver);
    }

    /** {@inheritdoc} */
    public function createDelayStage(
        RequestExecutorInterface $executor,
        EventCaller $caller
    ) {
        return new DelayStage($executor, $caller);
    }

    /** {@inheritdoc} */
    public function createIoStage(RequestExecutorInterface $executor, EventCaller $caller)
    {
        return new IoStage(
            $executor,
            $caller,
            [
                new ReadIoHandler(),
                new WriteIoHandler(),
                new SslHandshakeIoHandler(),
                new NullIoHandler()
            ]
        );
    }

    /** {@inheritdoc} */
    public function createDisconnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        AsyncSelector $selector = null
    ) {
        $disconnectStage = new DisconnectStage($executor, $caller, $selector);
        $guardianStage   = new GuardianStage($executor, $caller, $disconnectStage);

        return new CompositeStage(
            [
                $disconnectStage,
                $guardianStage
            ]
        );
    }
}
