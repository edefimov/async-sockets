<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\ExecutionContext;
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
        ExecutionContext $executionContext,
        EventCaller $caller,
        LimitationSolverInterface $limitationSolver
    ) {
        return new ConnectStageReturningAllActiveSockets($executor, $caller, $limitationSolver, $executionContext);
    }

    /** {@inheritdoc} */
    public function createDelayStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller
    ) {
        return new DelayStage($executor, $caller, $executionContext);
    }

    /** {@inheritdoc} */
    public function createIoStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller
    ) {
        $duplexHandler = new ReadWriteIoHandler();
        $handler       = new DelegatingIoHandler(
            [
                new ReadIoHandler(),
                new WriteIoHandler(),
                new SslHandshakeIoHandler(),
                new NullIoHandler(),
                new ReadWriteIoHandler()
            ]
        );

        $duplexHandler->setHandler($handler);

        return new IoStage(
            $executor,
            $caller,
            $executionContext,
            $handler
        );
    }

    /** {@inheritdoc} */
    public function createDisconnectStage(
        RequestExecutorInterface $executor,
        ExecutionContext $executionContext,
        EventCaller $caller,
        AsyncSelector $selector = null
    ) {
        $disconnectStage = new DisconnectStage($executor, $caller, $executionContext, $selector);
        $guardianStage   = new GuardianStage($executor, $caller, $executionContext, $disconnectStage);

        return new CompositeStage(
            [
                $disconnectStage,
                $guardianStage
            ]
        );
    }
}
