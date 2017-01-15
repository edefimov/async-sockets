<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\Event\Event;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\PersistentClientSocket;
use AsyncSockets\Socket\SocketInterface;
use AsyncSockets\Socket\StreamResourceInterface;

/**
 * Class RequestDescriptor
 */
class RequestDescriptor implements StreamResourceInterface, EventHandlerInterface
{
    /**
     * This descriptor is ready for reading
     */
    const RDS_READ = 0x0001;

    /**
     * This descriptor is ready for writing
     */
    const RDS_WRITE = 0x0002;

    /**
     * This descriptor is has OOB data
     */
    const RDS_OOB = 0x0004;

    /**
     * Minimum transfer rate counter for receiving data
     */
    const COUNTER_RECV_MIN_RATE = 'recv_speed_rate_counter';

    /**
     * Minimum transfer rate counter for sending data
     */
    const COUNTER_SEND_MIN_RATE = 'send_speed_rate_counter';

    /**
     * Socket for this operation
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * Key-value pairs with meta information
     *
     * @var array
     */
    private $metadata;

    /**
     * Event handler object
     *
     * @var EventHandlerInterface
     */
    private $handlers;

    /**
     * Flag whether this socket request is running
     *
     * @var bool
     */
    private $isRunning;

    /**
     * Operation to perform on socket
     *
     * @var OperationInterface
     */
    private $operation;

    /**
     * Flag if this socket is postponed
     *
     * @var bool
     */
    private $isPostponed = false;

    /**
     * Set of state flags: RDS_* consts
     *
     * @var int
     */
    private $state = 0;

    /**
     * Array of counters
     *
     * @var SpeedRateCounter[]
     */
    private $counters;

    /**
     * RequestDescriptor constructor.
     *
     * @param SocketInterface       $socket Socket object
     * @param OperationInterface    $operation Operation to perform on socket
     * @param array                 $metadata Metadata
     * @param EventHandlerInterface $handlers Handlers for this socket
     */
    public function __construct(
        SocketInterface $socket,
        OperationInterface $operation,
        array $metadata,
        EventHandlerInterface $handlers = null
    ) {
        $this->socket    = $socket;
        $this->operation = $operation;
        $this->metadata  = $metadata;
        $this->handlers  = $handlers;
        $this->counters  = [];
        $this->initialize();
    }

    /**
     * Initialize data before request
     *
     * @return void
     */
    public function initialize()
    {
        $this->isRunning = false;
    }

    /**
     * Return Operation
     *
     * @return OperationInterface
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * Sets Operation
     *
     * @param OperationInterface $operation New operation
     *
     * @return void
     */
    public function setOperation(OperationInterface $operation)
    {
        $this->operation = $operation;
    }

    /**
     * Return flag whether request is running
     *
     * @return boolean
     */
    public function isRunning()
    {
        return $this->isRunning;
    }

    /**
     * Sets running flag
     *
     * @param boolean $isRunning New value for IsRunning
     *
     * @return void
     */
    public function setRunning($isRunning)
    {
        $this->isRunning = $isRunning;
    }

    /**
     * Return Socket
     *
     * @return SocketInterface
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /** {@inheritdoc} */
    public function getStreamResource()
    {
        return $this->socket->getStreamResource();
    }

    /**
     * Return key-value array with metadata
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set metadata for given socket
     *
     * @param string|array    $key Either string or key-value array of metadata. If string, then value must be
     *                             passed in third argument, if array, then third argument will be ignored
     * @param mixed           $value Value for key or null, if $key is array
     *
     * @return void
     */
    public function setMetadata($key, $value = null)
    {
        if (!is_array($key)) {
            $this->metadata[$key] = $value;
        } else {
            $this->metadata = array_merge(
                $this->metadata,
                $key
            );
        }
    }

    /** {@inheritdoc} */
    public function invokeEvent(Event $event)
    {
        if ($this->handlers) {
            $this->handlers->invokeEvent($event);
        }
    }

    /**
     * Completes processing this socket in event loop, but keep this socket connection opened. Applicable
     * only to persistent sockets, all other socket types are ignored by this method.
     *
     * @return void
     */
    public function postpone()
    {
        if (!($this->socket instanceof PersistentClientSocket)) {
            return;
        }

        $this->isPostponed = true;
    }

    /**
     * Return true, if this socket shouldn't be processed by executor engine
     *
     * @return bool
     */
    public function isPostponed()
    {
        return $this->isPostponed;
    }

    /**
     * Return true if descriptor has given state
     *
     * @param int $state State to check, set of RDS_* consts
     *
     * @return bool
     */
    public function hasState($state)
    {
        return (bool) ($this->state & $state);
    }

    /**
     * Sets one state into an object
     *
     * @param int $state State to set, set of RDS_* consts
     *
     * @return void
     */
    public function setState($state)
    {
        $this->state |= $state;
    }

    /**
     * Clears given state in object
     *
     * @param int $state State to clear, set of RDS_* consts
     *
     * @return void
     */
    public function clearState($state)
    {
        $this->state &= ~$state;
    }

    /**
     * Registers counter in this descriptor
     *
     * @param string           $name SpeedRateCounter name for retrieving
     * @param SpeedRateCounter $counter A counter object
     *
     * @return void
     */
    public function registerCounter($name, SpeedRateCounter $counter)
    {
        if (!isset($this->counters[$name])) {
            $this->counters[$name] = $counter;
        }
    }

    /**
     * Return counter by given name
     *
     * @param string $name SpeedRateCounter name
     *
     * @return SpeedRateCounter|null
     */
    public function getCounter($name)
    {
        return isset($this->counters[$name]) ? $this->counters[$name] : null;
    }

    /**
     * Resets counter with given name
     *
     * @param string $name Counter name
     *
     * @return void
     */
    public function resetCounter($name)
    {
        $counter = $this->getCounter($name);
        if (!$counter) {
            return;
        }

        $resetMetadata = [
            self::COUNTER_RECV_MIN_RATE => [
                RequestExecutorInterface::META_RECEIVE_SPEED => 0,
            ],
            self::COUNTER_SEND_MIN_RATE => [
                RequestExecutorInterface::META_SEND_SPEED => 0,
            ],
        ];

        $counter->reset();
        if (isset($resetMetadata[$name])) {
            $this->setMetadata($resetMetadata[$name]);
        }
    }
}
