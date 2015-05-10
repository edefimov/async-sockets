<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\RequestExecutor\EventDispatcherAwareRequestExecutor;
use AsyncSockets\RequestExecutor\RequestExecutor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class AsyncSocketFactory
 *
 * @api
 */
class AsyncSocketFactory
{
    /**
     * Create client socket
     */
    const SOCKET_CLIENT = 'client';

    /**
     * Create socket client
     *
     * @param string $type Socket type to create, one of SOCKET_* consts
     *
     * @return SocketInterface
     * @throws \InvalidArgumentException If type parameter is unknown
     *
     * @api
     */
    public function createSocket($type = self::SOCKET_CLIENT)
    {
        switch ($type) {
            case self::SOCKET_CLIENT:
                return new ClientSocket();
            default:
                throw new \InvalidArgumentException("Unexpected type {$type} used in " . __FUNCTION__);
        }

    }

    /**
     * Create RequestExecutor object
     *
     * @return RequestExecutorInterface
     *
     * @api
     */
    public function createRequestExecutor()
    {
        if (interface_exists('Symfony\Component\EventDispatcher\EventDispatcherInterface', true)) {
            return new EventDispatcherAwareRequestExecutor();
        } else {
            return new RequestExecutor();
        }
    }

    /**
     * Create socket operation selector
     *
     * @return AsyncSelector
     *
     * @api
     */
    public function createSelector()
    {
        return new AsyncSelector();
    }
}
