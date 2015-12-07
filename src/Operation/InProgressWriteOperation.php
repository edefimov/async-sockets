<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Operation;

/**
 * Class InProgressWriteOperation
 */
class InProgressWriteOperation extends WriteOperation
{
    /**
     * Next operation
     *
     * @var OperationInterface
     */
    private $nextOperation;

    /**
     * WriteOperation constructor.
     *
     * @param OperationInterface $next Scheduled next write operation
     * @param string             $data Data to send
     */
    public function __construct(OperationInterface $next = null, $data = null)
    {
        parent::__construct($data);
        $this->nextOperation = $next;
    }

    /**
     * Return NextOperation
     *
     * @return OperationInterface
     */
    public function getNextOperation()
    {
        return $this->nextOperation;
    }
}
