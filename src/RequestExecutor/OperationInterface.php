<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

/**
 * Interface OperationInterface
 */
interface OperationInterface
{
    /**
     * Return operation type
     *
     * @return string One of RequestExecutorInterface::OPERATION_* consts
     */
     public function getType();
}
