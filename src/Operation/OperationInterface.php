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
 * Interface OperationInterface
 */
interface OperationInterface
{
    /**
     * Read operation
     */
    const OPERATION_READ = 'read';

    /**
     * Write operation
     */
    const OPERATION_WRITE = 'write';

    /**
     * Return operation type
     *
     * @return string One of OPERATION_* consts
     */
    public function getType();
}
