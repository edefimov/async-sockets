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
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class TimeoutStage
 */
class TimeoutStage extends AbstractStage
{
    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        /** @var OperationMetadata[] $operations */
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
