<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Operation;

/**
 * Class ReadWriteOperation. Provides reading and writing at the same time
 */
class ReadWriteOperation implements OperationInterface
{
    /**
     * Flag if read operations must be processed before write
     */
    const READ_FIRST = true;

    /**
     * Flag if write operations must be processed before read
     */
    const WRITE_FIRST = false;

    /**
     * Operation queue indexed by operation type
     *
     * @var OperationInterface[][]
     */
    private $queue = [];

    /**
     * Flag whether read operation must be fired before write
     *
     * @var bool
     */
    private $isReadFirst;

    /**
     * ReadWriteOperation constructor.
     *
     * @param bool                 $isReadFirst Flag whether read operation must be fired before write
     * @param OperationInterface[] $operations  Array of operations to schedule
     */
    public function __construct($isReadFirst, array $operations = [])
    {
        $this->isReadFirst = $isReadFirst;
        foreach ($operations as $operation) {
            $this->scheduleOperation($operation);
        }
    }

    /**
     * Set read or write operation
     *
     * @param OperationInterface $operation Operation to set
     *
     * @return void
     */
    public function scheduleOperation(OperationInterface $operation)
    {
        $key = spl_object_hash($operation);
        switch (true) {
            case $operation instanceof ReadOperation:
                $this->queue[OperationInterface::OPERATION_READ][$key] = $operation;
                break;
            case $operation instanceof WriteOperation:
                $this->queue[OperationInterface::OPERATION_WRITE][$key] = $operation;
                break;
            default:
                // no action
        }
    }

    /**
     * Mark operation as completed
     *
     * @param OperationInterface $operation Operation to mark as done
     *
     * @return void
     */
    public function markCompleted(OperationInterface $operation)
    {
        $key = spl_object_hash($operation);
        switch (true) {
            case $operation instanceof ReadOperation:
                unset($this->queue[OperationInterface::OPERATION_READ][$key]);
                break;
            case $operation instanceof WriteOperation:
                unset($this->queue[OperationInterface::OPERATION_WRITE][$key]);
                break;
            default:
                // no action
        }
    }

    /**
     * Return current read operation
     *
     * @return ReadOperation|null
     */
    public function getReadOperation()
    {
        return !empty($this->queue[OperationInterface::OPERATION_READ]) ?
            reset($this->queue[OperationInterface::OPERATION_READ]) :
            null;
    }

    /**
     * Return current write operation
     *
     * @return WriteOperation|null
     */
    public function getWriteOperation()
    {
        return !empty($this->queue[OperationInterface::OPERATION_WRITE]) ?
            reset($this->queue[OperationInterface::OPERATION_WRITE]) :
            null;
    }

    /**
     * Return true if read must be handled before write
     *
     * @return bool
     */
    public function isReadFirst()
    {
        return $this->isReadFirst;
    }

    /**
     * @inheritDoc
     */
    public function getTypes()
    {
        $result = [];
        foreach ($this->queue as $type => $operations) {
            if (!empty($operations)) {
                $result[] = $type;
            }
        }

        return $result;
    }
}
