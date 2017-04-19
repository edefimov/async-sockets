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

use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Metadata\SpeedRateCounter;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class AbstractOobHandler
 */
abstract class AbstractOobHandler implements IoHandlerInterface
{
    /**
     * Operation to descriptor state map
     *
     * @var array
     */
    private static $stateMap = [
        OperationInterface::OPERATION_READ  => RequestDescriptor::RDS_READ,
        OperationInterface::OPERATION_WRITE => RequestDescriptor::RDS_WRITE,
    ];

    /**
     * {@inheritdoc}
     */
    final public function handle(
        OperationInterface $operation,
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    ) {
        $result = $this->handleOobData($descriptor, $executor, $eventHandler, $executionContext);
        if ($result) {
            return $result;
        }

        $state = $this->getInvokeState($descriptor->getOperation());
        if ($descriptor->hasState($state)) {
            $descriptor->clearState($state);

            $result = $this->handleOperation(
                $descriptor->getOperation(),
                $descriptor,
                $executor,
                $eventHandler,
                $executionContext
            );
        }

        return $result;
    }

    /**
     * Return type of this handler
     *
     * @return int One of RequestDescriptor::RDS_* constant
     */
    abstract protected function getHandlerType();

    /**
     * Process given operation
     *
     * @param OperationInterface       $operation        Operation to process
     * @param RequestDescriptor        $descriptor       Request descriptor
     * @param RequestExecutorInterface $executor         Executor, processing operation
     * @param EventHandlerInterface    $eventHandler     Event handler for this operation
     * @param ExecutionContext         $executionContext Execution context
     *
     * @return OperationInterface|null Next operation to pass in socket. Return null,
     *      if next operation is not required. Return $operation parameter, if operation is not completed yet
     */
    abstract protected function handleOperation(
        OperationInterface $operation,
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    );

    /**
     * Return state which should invoke this operation
     *
     * @param OperationInterface $operation Operation object
     *
     * @return int Set of RequestDescriptor::RDS_* constant
     */
    private function getInvokeState(OperationInterface $operation)
    {
        $result = 0;
        foreach ($operation->getTypes() as $type) {
            $result |= isset(self::$stateMap[$type]) ? self::$stateMap[$type] : 0;
        }

        return $result & $this->getHandlerType();
    }

    /**
     * Handle OOB data
     *
     * @param RequestDescriptor        $descriptor       Request descriptor
     * @param RequestExecutorInterface $executor         Executor, processing operation
     * @param EventHandlerInterface    $eventHandler     Event handler for this operation
     * @param ExecutionContext         $executionContext Execution context
     *
     * @return OperationInterface|null Operation to return to user or null to continue normal processing
     */
    private function handleOobData(
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    ) {
        if (!$descriptor->hasState(RequestDescriptor::RDS_OOB)) {
            return null;
        }

        $descriptor->clearState(RequestDescriptor::RDS_OOB);

        $picker = new RawFramePicker();
        $socket = $descriptor->getSocket();
        $meta   = $descriptor->getMetadata();
        $frame  = $socket->read($picker, true);
        $event  = new ReadEvent(
            $executor,
            $socket,
            $meta[ RequestExecutorInterface::META_USER_CONTEXT ],
            $frame,
            true
        );

        $eventHandler->invokeEvent($event, $executor, $socket, $executionContext);

        return $event->getNextOperation();
    }

    /**
     * Creates transfer rate counter with given parameters
     *
     * @param string            $name Counter name to create
     * @param int               $minRate Minimum speed setting for counter
     * @param int               $duration Max duration of low speed
     * @param RequestDescriptor $descriptor Request descriptor for counter
     *
     * @return SpeedRateCounter
     */
    private function createRateCounter($name, $minRate, $duration, RequestDescriptor $descriptor)
    {
        $counter = $descriptor->getCounter($name);

        if (!$counter) {
            $meta    = $descriptor->getMetadata();
            $counter = new SpeedRateCounter($minRate, $duration);
            $time    = $meta[RequestExecutorInterface::META_LAST_IO_START_TIME] ?:
                        $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME];
            $counter->advance($time, 0);
            $descriptor->registerCounter($name, $counter);
        }

        return $counter;
    }

    /**
     * Process transfer bytes counter
     *
     * @param string            $name Counter name operating this transfer
     * @param RequestDescriptor $descriptor Socket descriptor
     * @param int               $bytes Amount of bytes processed by transfer
     *
     * @return void
     */
    protected function handleTransferCounter($name, RequestDescriptor $descriptor, $bytes)
    {
        $meta = $descriptor->getMetadata();

        $info = $this->getTransferCounterMeta($name, $meta);

        $descriptor->resetCounter($info['resetCounter']);

        $meta = $descriptor->getMetadata();
        $descriptor->setMetadata($info['bytesCounter'], $meta[$info['bytesCounter']] + $bytes);

        $counter = $descriptor->getCounter($name);
        try {
            if (!$counter) {
                $counter = $this->createRateCounter($name, $info['minSpeed'], $info['duration'], $descriptor);
            }

            $counter->advance(microtime(true), $bytes);
            $descriptor->setMetadata(
                $info['speedCounter'],
                $counter->getCurrentSpeed() ?: $meta[$info['speedCounter']]
            );
        } catch (\OverflowException $e) {
            $callable = $info['exception'];

            throw $callable(
                $descriptor->getSocket(),
                $counter->getCurrentSpeed(),
                $counter->getCurrentDuration()
            );
        }
    }

    /**
     * Return metadata for transfer rate counter
     *
     * @param string $name Counter name
     * @param array  $meta Socket metadata
     *
     * @return array
     */
    private function getTransferCounterMeta($name, array $meta)
    {
        $map = [
            RequestDescriptor::COUNTER_RECV_MIN_RATE => [
                'resetCounter' => RequestDescriptor::COUNTER_SEND_MIN_RATE,
                'minSpeed'     => $meta[ RequestExecutorInterface::META_MIN_RECEIVE_SPEED ],
                'duration'     => $meta[ RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION ],
                'bytesCounter' => RequestExecutorInterface::META_BYTES_RECEIVED,
                'speedCounter' => RequestExecutorInterface::META_RECEIVE_SPEED,
                'exception'    => [ 'AsyncSockets\Exception\SlowSpeedTransferException', 'tooSlowDataReceiving' ],
            ],
            RequestDescriptor::COUNTER_SEND_MIN_RATE => [
                'resetCounter' => RequestDescriptor::COUNTER_RECV_MIN_RATE,
                'minSpeed'     => $meta[ RequestExecutorInterface::META_MIN_SEND_SPEED ],
                'duration'     => $meta[ RequestExecutorInterface::META_MIN_SEND_SPEED_DURATION ],
                'bytesCounter' => RequestExecutorInterface::META_BYTES_SENT,
                'speedCounter' => RequestExecutorInterface::META_SEND_SPEED,
                'exception'    => [ 'AsyncSockets\Exception\SlowSpeedTransferException', 'tooSlowDataSending' ],
            ],
        ];

        if (!isset($map[ $name ])) {
            throw new \LogicException('Can not process counter ' . $name . ' in transfer operation');
        }

        return $map[ $name ];
    }
}
