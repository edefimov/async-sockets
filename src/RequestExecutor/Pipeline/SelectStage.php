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

use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\Specification\ConnectionLessSocketSpecification;
use AsyncSockets\Socket\AsyncSelector;

/**
 * Class SelectStageAbstract
 */
class SelectStage extends AbstractTimeAwareStage
{
    /**
     * Selector
     *
     * @var AsyncSelector
     */
    private $selector;

    /**
     * SelectStage constructor.
     *
     * @param RequestExecutorInterface $executor Request executor
     * @param EventCaller              $eventCaller Event caller
     * @param AsyncSelector            $selector Async selector
     */
    public function __construct(RequestExecutorInterface $executor, EventCaller $eventCaller, AsyncSelector $selector)
    {
        parent::__construct($executor, $eventCaller);
        $this->selector = $selector;
    }

    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        $this->initLastIoOperationInfo($operations);
        $udpOperations = $this->findConnectionLessSockets($operations);
        if ($udpOperations) {
            // do not perform actual select, since these operations must be processed immediately
            return $udpOperations;
        }

        /** @var OperationMetadata[] $operations */
        foreach ($operations as $operation) {
            $this->selector->addSocketOperation(
                $operation,
                $operation->getOperation()->getType()
            );
        }

        try {
            $timeout = $this->calculateSelectorTimeout($operations);
            $context = $this->selector->select($timeout['sec'], $timeout['microsec']);
            return array_merge(
                $context->getRead(),
                $context->getWrite()
            );
        } catch (TimeoutException $e) {
            return [];
        } catch (SocketException $e) {
            throw $e;
        }
    }

    /**
     * Initialize information about last I/O operation
     *
     * @param OperationMetadata[] $operations List of operations to apply
     *
     * @return void
     */
    private function initLastIoOperationInfo(array $operations)
    {
        /** @var OperationMetadata[] $operations */
        foreach ($operations as $operation) {
            $this->setSocketOperationTime($operation, RequestExecutorInterface::META_LAST_IO_START_TIME);
        }
    }

    /**
     * Calculate selector timeout according to given array of active socket keys
     *
     * @param OperationMetadata[] $activeOperations Active socket keys
     *
     * @return array { "sec": int, "microsec": int }
     */
    private function calculateSelectorTimeout(array $activeOperations)
    {
        $result    = [ 'sec' => 0, 'microsec' => 0 ];
        $timeList  = [];
        $microtime = microtime(true);

        $hasInfiniteTimeout = false;
        foreach ($activeOperations as $activeOperation) {
            $timeout            = $this->getSingleSocketTimeout($activeOperation, $microtime);
            $hasInfiniteTimeout = $hasInfiniteTimeout || $timeout === null;
            if ($timeout > 0) {
                $timeList[] = $timeout;
            }
        }

        $timeList = array_filter($timeList);
        if ($timeList) {
            $timeout = min($timeList);
            $result = [
                'sec'      => (int) floor($timeout),
                'microsec' => round((double) $timeout - floor($timeout), 6) * 1000000,
            ];
        } elseif ($hasInfiniteTimeout) {
            $result = [
                'sec'      => null,
                'microsec' => null,
            ];
        }

        return $result;
    }

    /**
     * Calculate timeout value for single socket operation
     *
     * @param OperationMetadata $operation Operation object
     * @param double $microTime Current time with microseconds
     *
     * @return double|null
     */
    private function getSingleSocketTimeout(OperationMetadata $operation, $microTime)
    {
        $desiredTimeout    = $this->timeoutSetting($operation);
        $lastOperationTime = $this->timeSinceLastIo($operation);

        if ($desiredTimeout === RequestExecutorInterface::WAIT_FOREVER) {
            return null;
        }

        return $lastOperationTime === null ?
            $desiredTimeout :
            $desiredTimeout - ($microTime - $lastOperationTime);
    }

    /**
     * Find operations with UdpClientSocket and return them as result
     *
     * @param OperationMetadata[] $operations List of all operations
     *
     * @return OperationMetadata[] List of udp "clients"
     */
    private function findConnectionLessSockets(array $operations)
    {
        $result        = [];
        $specification = new ConnectionLessSocketSpecification();
        foreach ($operations as $operation) {
            if ($specification->isSatisfiedBy($operation)) {
                $result[] = $operation;
            }
        }

        return $result;
    }
}
