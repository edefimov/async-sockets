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

use AsyncSockets\Event\DataAlertEvent;
use AsyncSockets\Exception\UnmanagedSocketException;
use AsyncSockets\Frame\EmptyFramePicker;
use AsyncSockets\Operation\NullOperation;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class GuardianStage
 */
class GuardianStage extends AbstractStage
{
    /**
     * Maximum socket attempts to execute
     */
    const MAX_ATTEMPTS_PER_SOCKET = 25;
    
    /**
     * Indexed by RequestDescriptor object hash array of attempts, after which we should kill connection
     *
     * @var int[]
     */
    private $candidates = [];

    /**
     * Disconnect stage
     *
     * @var DisconnectStage
     */
    private $disconnectStage;

    /**
     * GuardianStage constructor.
     *
     * @param RequestExecutorInterface $executor         Request executor
     * @param EventCaller              $eventCaller      Event caller
     * @param ExecutionContext         $executionContext Execution context
     * @param DisconnectStage          $disconnectStage  Disconnect stage
     */
    public function __construct(
        RequestExecutorInterface $executor,
        EventCaller $eventCaller,
        ExecutionContext $executionContext,
        DisconnectStage $disconnectStage
    ) {
        parent::__construct($executor, $eventCaller, $executionContext);
        $this->disconnectStage = $disconnectStage;
    }

    /** {@inheritdoc} */
    public function processStage(array $requestDescriptors)
    {
        $result = [];
        foreach ($requestDescriptors as $key => $descriptor) {
            if (!$this->handleDeadConnection($descriptor)) {
                $result[$key] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * Check if this request is alive and managed by client code
     *
     * @param RequestDescriptor $descriptor Object to test
     *
     * @return bool True if connection killed, false otherwise
     */
    private function handleDeadConnection(RequestDescriptor $descriptor)
    {
        $key = spl_object_hash($descriptor);
        if (!$this->isZombieCandidate($descriptor)) {
            unset($this->candidates[$key]);
            return false;
        }

        if (!isset($this->candidates[$key])) {
            $this->candidates[$key] = self::MAX_ATTEMPTS_PER_SOCKET;
        }

        $this->notifyDataAlert(
            $descriptor,
            self::MAX_ATTEMPTS_PER_SOCKET - $this->candidates[$key] + 1,
            self::MAX_ATTEMPTS_PER_SOCKET
        );

        --$this->candidates[$key];
        $result = false;
        if (!$this->candidates[$key]) {
            $result = true;
            $this->killZombieConnection($descriptor);
            unset($this->candidates[$key]);
        }

        return $result;
    }

    /**
     * Check if this socket can be a zombie
     *
     * @param RequestDescriptor $descriptor Descriptor object
     *
     * @return bool
     */
    private function isZombieCandidate(RequestDescriptor $descriptor)
    {
        $metadata  = $descriptor->getMetadata();
        if ($metadata[RequestExecutorInterface::META_REQUEST_COMPLETE]) {
            return false;
        }

        $operation = $descriptor->getOperation();
        return ($operation instanceof NullOperation) ||
               (
                   $operation instanceof ReadOperation &&
                   $operation->getFramePicker() instanceof EmptyFramePicker
               );
    }

    /**
     * Closes connection, as we suppose it is unmanaged
     *
     * @param RequestDescriptor $descriptor Descriptor object to kill
     *
     * @return void
     */
    private function killZombieConnection(RequestDescriptor $descriptor)
    {
        $this->eventCaller->callExceptionSubscribers(
            $descriptor,
            UnmanagedSocketException::zombieSocketDetected($descriptor->getSocket()),
            $this->executor,
            $this->executionContext
        );

        $this->disconnectStage->disconnect($descriptor);
    }

    /**
     * Notify client about unhandled data in socket
     *
     * @param RequestDescriptor $descriptor Socket operation descriptor
     * @param int               $attempt Current attempt number from 1
     * @param int               $totalAttempts Total attempts
     *
     * @return void
     */
    private function notifyDataAlert(
        RequestDescriptor $descriptor,
        $attempt,
        $totalAttempts
    ) {
        $socket = $descriptor->getSocket();
        $meta   = $this->executor->socketBag()->getSocketMetaData($socket);
        $event  = new DataAlertEvent(
            $this->executor,
            $socket,
            $meta[ RequestExecutorInterface::META_USER_CONTEXT ],
            $attempt,
            $totalAttempts
        );
        
        $this->callSocketSubscribers($descriptor, $event);

        $operation = $event->getNextOperation();
        if ($operation) {
            $descriptor->setOperation($operation);
        }
    }
}
