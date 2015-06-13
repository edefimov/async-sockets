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
        /** @var OperationMetadata[] $operations */
        foreach ($operations as $operation) {
            $this->setSocketOperationTime($operation, RequestExecutorInterface::META_LAST_IO_START_TIME);
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
        foreach ($activeOperations as $activeOperation) {
            $meta    = $activeOperation->getMetadata();
            $timeout = $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] === null ?
                            $this->getSingleSocketTimeout(
                                $microtime,
                                $meta[RequestExecutorInterface::META_CONNECTION_TIMEOUT],
                                $meta[RequestExecutorInterface::META_CONNECTION_START_TIME]
                            ) :
                            $this->getSingleSocketTimeout(
                                $microtime,
                                $meta[RequestExecutorInterface::META_IO_TIMEOUT],
                                $meta[RequestExecutorInterface::META_LAST_IO_START_TIME]
                            );

            if ($timeout > 0 || $timeout === null) {
                $timeList[] = $timeout;
            }
        }

        if ($timeList) {
            $timeList = array_filter($timeList);
            if ($timeList) {
                $timeout = min($timeList);
                $result = [
                    'sec'      => (int) floor($timeout),
                    'microsec' => round((double) $timeout - floor($timeout), 6) * 1000000,
                ];
            } else {
                $result = [
                    'sec'      => null,
                    'microsec' => null,
                ];
            }
        }

        return $result;
    }

    /**
     * Calculate timeout value for single socket operation
     *
     * @param double $microTime Current time with microseconds
     * @param double $desiredTimeout Timeout from settings
     * @param double $lastOperationTime Last operation timestamp
     *
     * @return double|null
     */
    private function getSingleSocketTimeout($microTime, $desiredTimeout, $lastOperationTime)
    {
        if ($desiredTimeout === RequestExecutorInterface::WAIT_FOREVER) {
            return null;
        }

        return $lastOperationTime === null ?
            $desiredTimeout :
            $desiredTimeout - ($microTime - $lastOperationTime);
    }
}
