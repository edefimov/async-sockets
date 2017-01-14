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
use AsyncSockets\RequestExecutor\IoHandlerInterface;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
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
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    ) {
        $result = $this->handleOobData($descriptor, $executor, $eventHandler);
        if ($result) {
            return $result;
        }

        $state = $this->getInvokeState($descriptor->getOperation());
        if ($descriptor->hasState($state)) {
            $descriptor->clearState($state);

            $result = $this->handleOperation($descriptor, $executor, $eventHandler);
        }

        return $result;
    }

    /**
     * Process given operation
     *
     * @param RequestDescriptor        $descriptor Request descriptor
     * @param RequestExecutorInterface $executor Executor, processing operation
     * @param EventHandlerInterface    $eventHandler Event handler for this operation
     *
     * @return OperationInterface|null Next operation to pass in socket. Return null,
     *      if next operation is not required. Return $operation parameter, if operation is not completed yet
     */
    abstract protected function handleOperation(
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
    );

    /**
     * Return state which should invoke this operation
     *
     * @param OperationInterface $operation Operation object
     *
     * @return int RequestDescriptor::RDS_* constant
     */
    private function getInvokeState(OperationInterface $operation)
    {
        $type = $operation->getType();

        return isset(self::$stateMap[$type]) ? self::$stateMap[$type] : 0;
    }

    /**
     * Handle OOB data
     *
     * @param RequestDescriptor        $descriptor Request descriptor
     * @param RequestExecutorInterface $executor Executor, processing operation
     * @param EventHandlerInterface    $eventHandler Event handler for this operation
     *
     * @return OperationInterface|null Operation to return to user or null to continue normal processing
     */
    private function handleOobData(
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler
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

        $eventHandler->invokeEvent($event);

        return $event->getNextOperation();
    }
}
