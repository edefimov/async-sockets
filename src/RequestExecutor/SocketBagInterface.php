<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Interface SocketBagInterface
 */
interface SocketBagInterface extends \Countable
{
    /**
     * Add socket into this bag
     *
     * @param SocketInterface       $socket Socket to add
     * @param OperationInterface    $operation Operation to perform on socket
     * @param array                 $metadata Socket metadata information, which will be passed
     *                                   to setSocketMetaData during this call
     * @param EventHandlerInterface $eventHandlers Optional handlers for this socket
     *
     * @return void
     * @throws \LogicException If socket has been already added
     * @see SocketBag::setSocketMetaData
     *
     * @api
     */
    public function addSocket(
        SocketInterface $socket,
        OperationInterface $operation,
        array $metadata = null,
        EventHandlerInterface $eventHandlers = null
    );

    /**
     * Return operation, associated with this socket
     *
     * @param SocketInterface $socket Socket to get operation for
     *
     * @return OperationInterface
     * @throws \OutOfBoundsException If given socket is not added to this bag
     */
    public function getSocketOperation(SocketInterface $socket);

    /**
     * Set new operation for this socket
     *
     * @param SocketInterface    $socket Socket object
     * @param OperationInterface $operation New operation
     *
     * @return void
     * @throws \OutOfBoundsException If given socket is not added to this bag
     */
    public function setSocketOperation(SocketInterface $socket, OperationInterface $operation);

    /**
     * Checks whether given socket was added to this executor
     *
     * @param SocketInterface $socket Socket object
     *
     * @return bool
     */
    public function hasSocket(SocketInterface $socket);

    /**
     * Remove socket from list
     *
     * @param SocketInterface $socket Socket to remove
     *
     * @return void
     * @throws \LogicException If you try to call this method when request is active and given socket hasn't been yet
     *                         processed
     *
     * @api
     */
    public function removeSocket(SocketInterface $socket);

    /**
     * Completes processing this socket in event loop, but keep this socket connection opened. Applicable
     * only to persistent sockets, all other socket types are ignored by this method.
     *
     * @param SocketInterface $socket Socket object
     *
     * @return void
     *
     * @api
     */
    public function postponeSocket(SocketInterface $socket);

    /**
     * Return array with meta information about socket
     *
     * @param SocketInterface $socket Added socket
     *
     * @return array Key-value array where key is one of META_* consts
     * @throws \OutOfBoundsException If given socket is not added to this bag
     *
     * @api
     */
    public function getSocketMetaData(SocketInterface $socket);

    /**
     * Set metadata for given socket
     *
     * @param SocketInterface $socket Added socket
     * @param string|array    $key Either string or key-value array of metadata. If string, then value must be
     *                             passed in third argument, if array, then third argument will be ignored
     * @param mixed           $value Value for key
     *
     * @return void
     * @throws \OutOfBoundsException If given socket is not added to this executor
     *
     * @api
     */
    public function setSocketMetaData(SocketInterface $socket, $key, $value = null);
}
