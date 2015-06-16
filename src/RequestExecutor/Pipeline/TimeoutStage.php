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
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

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
                $event = $this->createEvent($operation, EventType::TIMEOUT);
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
     * @param OperationMetadata $operation Operation object
     * @param double            $microTime Current time with microseconds
     *
     * @return bool True, if socket with this params in timeout, false otherwise
     */
    private function isSingleSocketTimeout(OperationMetadata $operation, $microTime)
    {
        $desiredTimeout    = $this->timeoutSetting($operation);
        $lastOperationTime = $this->timeSinceLastIo($operation);

        return ($desiredTimeout !== RequestExecutorInterface::WAIT_FOREVER) &&
               ($microTime - $lastOperationTime > $desiredTimeout);
    }
}
