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

use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
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
     * @param RequestExecutorInterface  $executor Request executor
     * @param EventCaller               $eventCaller Event caller
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
    public function processStage(array $requestDescriptors)
    {
        /** @var RequestDescriptor[] $requestDescriptors */
        $totalItems = count($requestDescriptors);
        $result     = [];
        foreach ($requestDescriptors as $descriptor) {
            $decision = $this->decide($descriptor, $totalItems);
            if ($decision === LimitationSolverInterface::DECISION_PROCESS_SCHEDULED) {
                break;
            } elseif ($decision === LimitationSolverInterface::DECISION_SKIP_CURRENT) {
                continue;
            } elseif ($decision !== LimitationSolverInterface::DECISION_OK) {
                throw new \LogicException('Unknown decision ' . $decision . ' received.');
            }

            if ($this->connectSocket($descriptor)) {
                $result[] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * Decide how to process given operation
     *
     * @param RequestDescriptor $requestDescriptor Operation to decide
     * @param int               $totalItems Total amount of pending requestDescriptors
     *
     * @return int One of LimitationSolverInterface::DECISION_* consts
     */
    private function decide(RequestDescriptor $requestDescriptor, $totalItems)
    {
        $meta = $requestDescriptor->getMetadata();
        if ($requestDescriptor->isRunning()) {
            return LimitationSolverInterface::DECISION_SKIP_CURRENT;
        }

        $isSkippingThis = $meta[RequestExecutorInterface::META_CONNECTION_START_TIME] !== null;

        if ($isSkippingThis) {
            return LimitationSolverInterface::DECISION_SKIP_CURRENT;
        }

        $decision = $this->decider->decide($this->executor, $requestDescriptor->getSocket(), $totalItems);
        if ($decision !== LimitationSolverInterface::DECISION_OK) {
            return $decision;
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
     * Start connecting process
     *
     * @param RequestDescriptor $descriptor Socket operation data
     *
     * @return bool True if successfully connected, false otherwise
     */
    private function connectSocket(RequestDescriptor $descriptor)
    {
        $descriptor->initialize();

        $socket = $descriptor->getSocket();
        $event  = $this->createEvent($descriptor, EventType::INITIALIZE);

        try {
            $this->callSocketSubscribers($descriptor, $event);
            $this->setSocketOperationTime($descriptor, RequestExecutorInterface::META_CONNECTION_START_TIME);

            $meta = $descriptor->getMetadata();

            $socket->open(
                $meta[ RequestExecutorInterface::META_ADDRESS ],
                $this->getStreamContextFromMetaData($meta)
            );

            $descriptor->setRunning(true);

            $result = true;
        } catch (SocketException $e) {
            $descriptor->setMetadata(RequestExecutorInterface::META_REQUEST_COMPLETE, true);
            $this->callExceptionSubscribers($descriptor, $e);

            $result = false;
        }

        return $result;
    }
}
