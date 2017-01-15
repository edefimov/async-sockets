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

use AsyncSockets\Event\TimeoutEvent;
use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\ServerSocket;

/**
 * Class TimeoutStage
 */
class TimeoutStage extends AbstractTimeAwareStage
{
    /** {@inheritdoc} */
    public function processStage(array $requestDescriptors)
    {
        /** @var RequestDescriptor[] $requestDescriptors */
        $result    = [ ];
        $microTime = microtime(true);
        foreach ($requestDescriptors as $key => $descriptor) {
            $isTimeout = $this->isSingleSocketTimeout($descriptor, $microTime) &&
                         !$this->handleTimeoutOnDescriptor($descriptor);
            if ($isTimeout) {
                $result[$key] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * Fire timeout event and processes user response
     *
     * @param RequestDescriptor $descriptor
     *
     * @return bool True if we may do one more attempt, false otherwise
     */
    public function handleTimeoutOnDescriptor(RequestDescriptor $descriptor)
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
            $result = $event->isNextAttemptEnabled();
        } catch (SocketException $e) {
            $this->callExceptionSubscribers($descriptor, $e);
            $result = false;
        }

        if ($result) {
            $this->updateMetadataForAttempt($descriptor, $event->when());
        }

        return $result;
    }

    /**
     * Update data inside descriptor to make one more attempt
     *
     * @param RequestDescriptor $descriptor Operation descriptor
     * @param string            $when When Timeout occurerd, one of TimeoutEvent::DURING_* consts
     *
     * @return void
     */
    private function updateMetadataForAttempt(RequestDescriptor $descriptor, $when)
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
     * @param RequestDescriptor $descriptor Descriptor object
     * @param double            $microTime Current time with microseconds
     *
     * @return bool True, if socket with this params in timeout, false otherwise
     */
    private function isSingleSocketTimeout(RequestDescriptor $descriptor, $microTime)
    {
        $desiredTimeout    = $this->timeoutSetting($descriptor);
        $lastOperationTime = $this->timeSinceLastIo($descriptor);
        $hasConnected      = $this->hasConnected($descriptor);

        return ($desiredTimeout !== RequestExecutorInterface::WAIT_FOREVER) &&
               (
                   ($hasConnected && $lastOperationTime !== null) ||
                   !$hasConnected
               ) &&
               ($microTime - $lastOperationTime >= $desiredTimeout)
               ;
    }
}
