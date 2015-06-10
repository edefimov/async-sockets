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

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\SelectContext;

/**
 * Class SelectStageAbstract
 */
class SelectStage extends AbstractTimeAwareStage
{
    /**
     * Perform select operation on active sockets
     *
     * @param OperationMetadata[] $activeOperations List of active operations
     * @param AsyncSelector       $selector Selector object
     *
     * @return SelectContext|null Return null, if timeout occurred
     * @throws SocketException If network operation failed
     */
    public function processSelect(array $activeOperations, AsyncSelector $selector)
    {
        foreach ($activeOperations as $activeOperation) {
            $this->setSocketOperationTime($activeOperation, RequestExecutorInterface::META_LAST_IO_START_TIME);
            $selector->addSocketOperation(
                $activeOperation->getSocket(),
                $activeOperation->getOperation()->getType()
            );
        }

        try {
            $timeout = $this->calculateSelectorTimeout($activeOperations);
            return $selector->select($timeout['sec'], $timeout['microsec']);
        } catch (TimeoutException $e) {
            // do nothing
        } catch (SocketException $e) {
            throw $e;
        }

        return null;
    }

    /**
     * Check given sockets to timeout
     *
     * @param OperationMetadata[] $operations Array of operations
     *
     * @return OperationMetadata[] Array of timeout operations
     */
    public function processTimeoutSockets(array $operations)
    {
        $result = [];
        foreach ($operations as $key => $operation) {
            $meta      = $operation->getMetadata();
            $microTime = microtime(true);
            $isTimeout =
                (
                    $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] === null &&
                    $this->isSingleSocketTimeout(
                        $microTime,
                        $meta[RequestExecutorInterface::META_CONNECTION_TIMEOUT],
                        $meta[RequestExecutorInterface::META_CONNECTION_START_TIME]
                    )
                ) || (
                    $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] !== null &&
                    $this->isSingleSocketTimeout(
                        $microTime,
                        $meta[RequestExecutorInterface::META_IO_TIMEOUT],
                        $meta[RequestExecutorInterface::META_LAST_IO_START_TIME]
                    )
                );

            if ($isTimeout) {
                $socket = $operation->getSocket();
                $event  = new Event(
                    $this->executor,
                    $socket,
                    $meta[RequestExecutorInterface::META_USER_CONTEXT],
                    EventType::TIMEOUT
                );
                try {
                    $this->callSocketSubscribers($operation, $event);
                } catch (SocketException $e) {
                    $this->callExceptionSubscribers($operation, $e, $event);
                }

                $result[$key] = $operation;
            }
        }

        return $result;
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
                    'microsec' => round((double) $timeout - floor($timeout), 6) * 1000000
                ];
            } else {
                $result = [
                    'sec'      => null,
                    'microsec' => null
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

    /**
     * Checks whether given params lead to timeout
     *
     * @param double $microTime Current time with microseconds
     * @param double $desiredTimeout Timeout from settings
     * @param double $lastOperationTime Last operation timestamp
     *
     * @return bool True, if socket with this params in timeout, false otherwise
     */
    private function isSingleSocketTimeout($microTime, $desiredTimeout, $lastOperationTime)
    {
        return ($desiredTimeout !== RequestExecutorInterface::WAIT_FOREVER) &&
               ($microTime - $lastOperationTime > $desiredTimeout);
    }
}
