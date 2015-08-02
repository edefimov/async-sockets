<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\LibEvent;

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;

/**
 * Interface LeCallbackInterface
 */
interface LeCallbackInterface
{
    /**
     * Read event
     */
    const EVENT_READ = 'read';

    /**
     * Write event
     */
    const EVENT_WRITE = 'write';

    /**
     * Timeout event
     */
    const EVENT_TIMEOUT = 'timeout';

    /**
     * Handle event from libevent
     *
     * @param OperationMetadata $operationMetadata Operation metadata object
     * @param string            $type One of EVENT_* consts
     *
     */
    public function onEvent(OperationMetadata $operationMetadata, $type);
}
