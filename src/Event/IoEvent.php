<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Event;

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\WriteOperation;

/**
 * Class IoEvent
 *
 * @api
 */
class IoEvent extends Event
{
    /**
     * Next operation to perform on socket
     *
     * @var OperationInterface|null
     */
    private $nextOperation;

    /**
     * Return next operation on socket
     *
     * @return OperationInterface|null Null means no further operation required
     */
    public function getNextOperation()
    {
        return $this->nextOperation;
    }

    /**
     * Mark next operation as read
     *
     * @param FramePickerInterface $framePicker Frame picker for reading data
     *
     * @return void
     */
    public function nextIsRead(FramePickerInterface $framePicker = null)
    {
        $this->nextIs(new ReadOperation($framePicker));
    }

    /**
     * Mark next operation as write
     *
     * @param string $data Data to write
     *
     * @return void
     */
    public function nextIsWrite($data = null)
    {
        $this->nextIs(new WriteOperation($data));
    }

    /**
     * Changed next operation to given one
     *
     * @param OperationInterface $operation Next operation
     *
     * @return void
     */
    public function nextIs(OperationInterface $operation)
    {
        $this->nextOperation = $operation;
    }

    /**
     * Mark next operation as not required
     *
     * @return void
     */
    public function nextOperationNotRequired()
    {
        $this->nextOperation = null;
    }
}
