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

use AsyncSockets\RequestExecutor\RequestExecutorInterface;

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
     * @var string
     */
    private $nextOperation;

    /**
     * Return next operation on socket
     *
     * @return string|null Null means no further operation required
     */
    public function getNextOperation()
    {
        return $this->nextOperation;
    }

    /**
     * Mark next operation as read
     *
     * @return void
     */
    public function nextIsRead()
    {
        $this->nextOperation = RequestExecutorInterface::OPERATION_READ;
    }

    /**
     * Mark next operation as write
     *
     * @return void
     */
    public function nextIsWrite()
    {
        $this->nextOperation = RequestExecutorInterface::OPERATION_WRITE;
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
