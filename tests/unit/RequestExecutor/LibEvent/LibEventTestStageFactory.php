<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\RequestExecutor\LibEvent;

use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface;
use AsyncSockets\RequestExecutor\Pipeline\StageFactoryInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Class LibEventTestStageFactory
 */
class LibEventTestStageFactory implements StageFactoryInterface
{
    /**
     * Connect stage
     *
     * @var PipelineStageInterface
     */
    private $connectStage;

    /**
     * I/O stage
     *
     * @var PipelineStageInterface
     */
    private $ioStage;

    /**
     * Disconnect stage
     *
     * @var PipelineStageInterface
     */
    private $disconnectStage;

    /** {@inheritdoc} */
    public function createConnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        LimitationSolverInterface $limitationSolver
    ) {
        return $this->connectStage;
    }

    /** {@inheritdoc} */
    public function createIoStage(RequestExecutorInterface $executor, EventCaller $caller)
    {
        return $this->ioStage;
    }

    /** {@inheritdoc} */
    public function createDisconnectStage(
        RequestExecutorInterface $executor,
        EventCaller $caller,
        AsyncSelector $selector = null
    ) {
        return $this->disconnectStage;
    }

    /**
     * Sets ConnectStage
     *
     * @param PipelineStageInterface $connectStage New value for ConnectStage
     *
     * @return void
     */
    public function setConnectStage(PipelineStageInterface $connectStage)
    {
        $this->connectStage = $connectStage;
    }

    /**
     * Sets DisconnectStage
     *
     * @param PipelineStageInterface $disconnectStage New value for DisconnectStage
     *
     * @return void
     */
    public function setDisconnectStage(PipelineStageInterface $disconnectStage)
    {
        $this->disconnectStage = $disconnectStage;
    }

    /**
     * Sets IoStage
     *
     * @param PipelineStageInterface $ioStage New value for IoStage
     *
     * @return void
     */
    public function setIoStage(PipelineStageInterface $ioStage)
    {
        $this->ioStage = $ioStage;
    }
}
