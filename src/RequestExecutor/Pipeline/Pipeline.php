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

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Metadata\SocketBag;

/**
 * Class Pipeline
 */
class Pipeline
{
    /**
     * DisconnectStage
     *
     * @var PipelineStageInterface
     */
    private $disconnectStage;

    /**
     * Connect stage
     *
     * @var PipelineStageInterface
     */
    private $connectStage;

    /**
     * PipelineStageInterface
     *
     * @var PipelineStageInterface[]
     */
    private $stages;

    /**
     * Pipeline constructor
     *
     * @param PipelineStageInterface   $connectStage Connect stage
     * @param PipelineStageInterface[] $stages Pipeline stages
     * @param PipelineStageInterface   $disconnectStage Disconnect stages
     */
    public function __construct(
        PipelineStageInterface $connectStage,
        array $stages,
        PipelineStageInterface $disconnectStage
    ) {
        $this->connectStage    = $connectStage;
        $this->stages          = $stages;
        $this->disconnectStage = $disconnectStage;
    }

    /**
     * Process I/O operations on sockets
     *
     * @param SocketBag $socketBag Socket bag
     *
     * @return void
     * @throws \Exception
     */
    public function process(SocketBag $socketBag)
    {
        $this->isRequestStopInProgress = false;
        $this->isRequestStopped        = false;

        do {
            $activeOperations = $this->connectStage->processStage($socketBag->getItems());
            if (!$activeOperations) {
                break;
            }

            foreach ($this->stages as $stage) {
                $activeOperations = $stage->processStage($activeOperations);
            }
        } while (true);
    }

    /**
     * Disconnect given list of sockets
     *
     * @param OperationMetadata[] $items Sockets' operations
     *
     * @return void
     */
    public function disconnectSockets(array $items)
    {
        $this->disconnectStage->processStage($items);
    }
}
