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

use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
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
    public function processStage(array $requestDescriptors)
    {
        $this->initLastIoOperationInfo($requestDescriptors);
        $udpOperations = $this->findConnectionLessSockets($requestDescriptors);
        if ($udpOperations) {
            // do not perform actual select, since these requestDescriptors must be processed immediately
            return $udpOperations;
        }

        /** @var RequestDescriptor[] $requestDescriptors */
        foreach ($requestDescriptors as $descriptor) {
            $this->selector->addSocketOperation(
                $descriptor,
                $descriptor->getOperation()->getType()
            );
        }

        try {
            $timeout = $this->calculateSelectorTimeout($requestDescriptors);
            $context = $this->selector->select($timeout['sec'], $timeout['microsec']);
            return array_merge(
                $context->getRead(),
                $context->getWrite()
            );
        } catch (TimeoutException $e) {
            return [];
        }
    }

    /**
     * Initialize information about last I/O operation
     *
     * @param RequestDescriptor[] $requestDescriptors List of requestDescriptors to apply
     *
     * @return void
     */
    private function initLastIoOperationInfo(array $requestDescriptors)
    {
        foreach ($requestDescriptors as $descriptor) {
            $this->setSocketOperationTime($descriptor, RequestExecutorInterface::META_LAST_IO_START_TIME);
        }
    }

    /**
     * Calculate selector timeout according to given array of active socket keys
     *
     * @param RequestDescriptor[] $activeDescriptors Active socket keys
     *
     * @return array { "sec": int, "microsec": int }
     */
    private function calculateSelectorTimeout(array $activeDescriptors)
    {
        $microtime  = microtime(true);
        $minTimeout = null;
        $result     = [
            'sec'      => null,
            'microsec' => null,
        ];

        foreach ($activeDescriptors as $descriptor) {
            $timeout    = $this->getSingleSocketTimeout($descriptor, $microtime);
            $minTimeout = $this->getMinTimeout($timeout, $minTimeout);
        }

        if ($minTimeout !== null) {
            $result = [
                'sec'      => (int) floor($minTimeout),
                'microsec' => round((double) $minTimeout - floor($minTimeout), 6) * 1000000,
            ];
        }

        return $result;
    }

    /**
     * Return minimum timeout from two values
     *
     * @param double $newValue New value
     * @param double $oldValue Old value
     *
     * @return double
     */
    private function getMinTimeout($newValue, $oldValue)
    {
        return (($newValue > 0 && $newValue < $oldValue) || $oldValue === null) ?
            $newValue :
            $oldValue;
    }

    /**
     * Calculate timeout value for single socket operation
     *
     * @param RequestDescriptor $operation Operation object
     * @param double            $microTime Current time with microseconds
     *
     * @return double|null
     */
    private function getSingleSocketTimeout(RequestDescriptor $operation, $microTime)
    {
        $desiredTimeout    = $this->timeoutSetting($operation);
        $lastOperationTime = $this->timeSinceLastIo($operation);

        if ($desiredTimeout === RequestExecutorInterface::WAIT_FOREVER) {
            return null;
        }

        $result = $lastOperationTime === null ?
            $desiredTimeout :
            $desiredTimeout - ($microTime - $lastOperationTime);

        return $result >= 0 ? $result : 0;
    }

    /**
     * Find descriptors with UdpClientSocket and return them as result
     *
     * @param RequestDescriptor[] $requestDescriptors List of all requestDescriptors
     *
     * @return RequestDescriptor[] List of udp "clients"
     */
    private function findConnectionLessSockets(array $requestDescriptors)
    {
        $result        = [];
        $specification = new ConnectionLessSocketSpecification();
        foreach ($requestDescriptors as $operation) {
            if ($specification->isSatisfiedBy($operation)) {
                $result[] = $operation;
            }
        }

        return $result;
    }
}
