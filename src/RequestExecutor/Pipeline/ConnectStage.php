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

use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ConnectStageAbstract
 */
class ConnectStage extends AbstractTimeAwareStage
{
    /**
     * LimitationSolverInterface
     *
     * @var LimitationSolverInterface
     */
    private $decider;

    /**
     * ConnectStageAbstract constructor.
     *
     * @param RequestExecutorInterface   $executor Request executor
     * @param EventCaller                $eventCaller Event caller
     * @param LimitationSolverInterface $decider Limitation solver for running requests
     */
    public function __construct(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        LimitationSolverInterface $decider
    ) {
        parent::__construct($executor, $eventCaller);
        $this->decider = $decider;
    }

    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        /** @var OperationMetadata[] $operations */
        $totalItems = count($operations);
        foreach ($operations as $item) {
            $decision = $this->decide($item, $totalItems);
            if ($decision === LimitationSolverInterface::DECISION_PROCESS_SCHEDULED) {
                break;
            } elseif ($decision === LimitationSolverInterface::DECISION_SKIP_CURRENT) {
                continue;
            } elseif ($decision !== LimitationSolverInterface::DECISION_OK) {
                throw new \LogicException('Unknown decision ' . $decision . ' received.');
            }

            $this->connectSocket($item);
        }

        return $this->getActiveOperations($operations);
    }

    /**
     * Decide how to process given operation
     *
     * @param OperationMetadata $operationMetadata Operation to decide
     * @param int               $totalItems Total amount of pending operations
     *
     * @return int One of LimitationSolverInterface::DECISION_* consts
     */
    private function decide(OperationMetadata $operationMetadata, $totalItems)
    {
        if ($operationMetadata->isRunning()) {
            return LimitationSolverInterface::DECISION_SKIP_CURRENT;
        }

        $decision = $this->decider->decide($this->executor, $operationMetadata->getSocket(), $totalItems);
        if ($decision !== LimitationSolverInterface::DECISION_OK) {
            return $decision;
        }

        $meta           = $operationMetadata->getMetadata();
        $isSkippingThis = (
            $meta[RequestExecutorInterface::META_CONNECTION_START_TIME] !== null ||
            $meta[RequestExecutorInterface::META_REQUEST_COMPLETE]
        );
        if ($isSkippingThis) {
            return LimitationSolverInterface::DECISION_SKIP_CURRENT;
        }

        return LimitationSolverInterface::DECISION_OK;
    }

    /**
     * Return stream context from meta data
     *
     * @param array $meta Socket metadata
     *
     * @return resource|null
     */
    private function getStreamContextFromMetaData($meta)
    {
        $metaStreamContext = $meta[ RequestExecutorInterface::META_SOCKET_STREAM_CONTEXT ];
        if (is_resource($metaStreamContext)) {
            return $metaStreamContext;
        } elseif (is_array($metaStreamContext)) {
            return stream_context_create(
                isset($metaStreamContext[ 'options' ]) ? $metaStreamContext[ 'options' ] : [ ],
                isset($metaStreamContext[ 'params' ]) ? $metaStreamContext[ 'params' ] : [ ]
            );
        }

        return null;
    }

    /**
     * Return array of keys for socket waiting for processing
     *
     * @param OperationMetadata[] $operations List of all operations
     *
     * @return OperationMetadata[]
     */
    private function getActiveOperations(array $operations)
    {
        $result = [];
        foreach ($operations as $key => $item) {
            $meta = $item->getMetadata();
            if (!$meta[RequestExecutorInterface::META_REQUEST_COMPLETE] && $item->isRunning()) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Start connecting process
     *
     * @param OperationMetadata $item Socket operation data
     *
     * @return void
     */
    private function connectSocket(OperationMetadata $item)
    {
        $item->initialize();

        $socket = $item->getSocket();
        $meta   = $item->getMetadata();
        $event  = $this->createEvent($item, EventType::INITIALIZE);

        try {
            $this->callSocketSubscribers($item, $event);
            $this->setSocketOperationTime($item, RequestExecutorInterface::META_CONNECTION_START_TIME);
            $socket->setBlocking(false);

            if (!$socket->getStreamResource()) {
                $socket->open(
                    $meta[ RequestExecutorInterface::META_ADDRESS ],
                    $this->getStreamContextFromMetaData($meta)
                );
            }

            $item->setRunning(true);
        } catch (SocketException $e) {
            $item->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
            $this->callExceptionSubscribers($item, $e, $event);
        }
    }
}
