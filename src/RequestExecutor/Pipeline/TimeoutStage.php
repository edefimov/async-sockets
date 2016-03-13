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

use AsyncSockets\Event\TimeoutEvent;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\ServerSocket;

/**
 * Class TimeoutStage
 */
class TimeoutStage extends AbstractTimeAwareStage
{
    /** {@inheritdoc} */
    public function processStage(array $operations)
    {
        /** @var OperationMetadata[] $operations */
        $result    = [ ];
        $microTime = microtime(true);
        foreach ($operations as $key => $operation) {
            if ($this->isSingleSocketTimeout($operation, $microTime)) {
                if (!$this->handleTimeoutOnDescriptor($operation)) {
                    $result[$key] = $operation;
                }
            }
        }

        return $result;
    }

    /**
     * Fire timeout event and processes user response
     *
     * @param OperationMetadata $descriptor
     *
     * @return bool True if we may do one more attempt, false otherwise
     */
    public function handleTimeoutOnDescriptor(OperationMetadata $descriptor)
    {
        $meta  = $descriptor->getMetadata();
        $event = new TimeoutEvent(
            $this->executor,
            $descriptor->getSocket(),
            $meta[RequestExecutorInterface::META_USER_CONTEXT],
            $meta[RequestExecutorInterface::META_CONNECTION_FINISH_TIME] !== null &&
            !($descriptor->getSocket() instanceof ServerSocket) ?
                TimeoutEvent::DURING_IO :
                TimeoutEvent::DURING_CONNECTION
        );
        try {
            $this->callSocketSubscribers($descriptor, $event);
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($descriptor, $e);
        }

        $result = $event->isNextAttemptEnabled();
        if ($result) {
            $this->updateMetadataForAttempt($descriptor, $event->when());
        }

        return $result;
    }

    /**
     * Update data inside descriptor to make one more attempt
     *
     * @param OperationMetadata $descriptor Operation descriptor
     * @param string            $when When Timeout occurerd, one of TimeoutEvent::DURING_* consts
     *
     * @return void
     */
    private function updateMetadataForAttempt(OperationMetadata $descriptor, $when)
    {
        switch ($when) {
            case TimeoutEvent::DURING_IO:
                $descriptor->setMetadata(RequestExecutorInterface::META_LAST_IO_START_TIME, null);
                break;
            case TimeoutEvent::DURING_CONNECTION:
                $descriptor->setRunning(false);
                $descriptor->setMetadata(
                    [
                        RequestExecutorInterface::META_LAST_IO_START_TIME     => null,
                        RequestExecutorInterface::META_CONNECTION_START_TIME  => null,
                        RequestExecutorInterface::META_CONNECTION_FINISH_TIME => null,
                    ]
                );
                break;
        }
    }

    /**
     * Checks whether given params lead to timeout
     *
     * @param OperationMetadata $operation Operation object
     * @param double            $microTime Current time with microseconds
     *
     * @return bool True, if socket with this params in timeout, false otherwise
     */
    private function isSingleSocketTimeout(OperationMetadata $operation, $microTime)
    {
        $desiredTimeout    = $this->timeoutSetting($operation);
        $lastOperationTime = $this->timeSinceLastIo($operation);
        $hasConnected      = $this->hasConnected($operation);

        return ($desiredTimeout !== RequestExecutorInterface::WAIT_FOREVER) &&
               (
                   ($hasConnected && $lastOperationTime !== null) ||
                   !$hasConnected
               ) &&
               ($microTime - $lastOperationTime >= $desiredTimeout)
               ;
    }
}
