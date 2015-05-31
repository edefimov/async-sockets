<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Event;

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\WriteOperation;

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
     * @param FrameInterface $frame Frame to read on next operation
     *
     * @return void
     */
    public function nextIsRead(FrameInterface $frame = null)
    {
        $this->nextOperation = new ReadOperation($frame);
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
        $this->nextOperation = new WriteOperation($data);
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
