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

use AsyncSockets\Socket\SocketInterface;

/**
 * Interface RequestExecutorInterface
 *
 * @api
 */
interface RequestExecutorInterface
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
     * Next I/O operation to perform on socket
     */
    const META_OPERATION = 'operation';

    /**
     * Flag indicating that execute operation on this socket is complete. Read-only value
     */
    const META_REQUEST_COMPLETE = 'isRequestComplete';

    /**
     * Float value with microseconds when connection process has begun. If connection process hasn't started
     * yet, the value will be null. Read-only value
     */
    const META_CONNECTION_START_TIME = 'connectStartTime';

    /**
     * Float value with microseconds when connection process has ended. If connection process hasn't finished
     * yet, the value will be null. Read-only value
     */
    const META_CONNECTION_FINISH_TIME = 'connectFinishTime';

    /**
     * Float value with microseconds when last io operation has started. Read-only value
     */
    const META_LAST_IO_START_TIME = 'lastIoStartTime';

    /**
     * Any user-defined value, not used internally at all.
     */
    const META_USER_CONTEXT = 'userContext';

    /**
     * Address to connect for socket. Has meaning only while execute process hasn't started. If this key is
     * specified, you can omit processing of EventType::OPEN event
     *
     * @see EventType::INITIALIZE
     */
    const META_ADDRESS = 'address';

    /**
     * Any valid stream context created by stream_context_create function or null or array with options.
     * Will be passed to the socket open method. If array value is used, then it should contain two nested keys:
     * "options" and "params", which will be passed to stream_context_create parameters respectively.
     * Be careful with this value, since it will be checked only by php engine during socket creation process
     *
     * @see SocketInterface::open
     * @see stream_context_create
     */
    const META_SOCKET_STREAM_CONTEXT = 'socketStreamContext';

    /**
     * Int value in seconds, default is get from default_socket_timeout php.ini setting.
     * If there was no connection during this period, socket
     * would be close automatically
     */
    const META_CONNECTION_TIMEOUT = 'connectTimeout';

    /**
     * Float value in seconds for waiting read or write data. Float part is used as microseconds for waiting operation
     * Default value will be the same as META_CONNECTION_TIMEOUT
     *
     * @see RequestExecutorInterface::META_CONNECTION_TIMEOUT
     */
    const META_IO_TIMEOUT = 'ioTimeout';

    /**
     * Add socket into this object
     *
     * @param SocketInterface $socket    Socket to add
     * @param string          $operation One of OPERATION_* consts
     * @param array           $metadata  Socket metadata information, which will be passed to setSocketMetaData
     *                                   during this call
     *
     * @return void
     * @throws \LogicException If socket has been already added
     * @see RequestExecutorInterface::setSocketMetaData
     *
     * @api
     */
    public function addSocket(SocketInterface $socket, $operation, array $metadata = null);

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
     * Return array with meta information about socket
     *
     * @param SocketInterface $socket Added socket
     *
     * @return array Key-value array where key is one of META_* consts
     * @throws \OutOfBoundsException If given socket is not added to this executor
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

    /**
     * Subscribe on socket processing event
     *
     * @param array           $events Events to handle. Key is one of EventType::* consts, value
     *          is the list of callable. Callable will receive any subclass of Event as the only argument
     * @param SocketInterface $socket Socket for subscribing. If not provided, than subscriber will be called for
     *          each socket in this executor, which doesn't have own subscriber. Socket
     *          must be added to this executor or \OutOfBoundsException will be thrown
     *
     * @return void
     *
     * @throws \OutOfBoundsException If given socket is not added to this executor
     * @see AsyncSockets\Event\EventType
     * @see AsyncSockets\Event\Event
     *
     * @api
     */
    public function addHandler(array $events, SocketInterface $socket = null);

    /**
     * Remove previously registered handlers
     *
     * @param array           $events Events to unsubscribe. Key is one of EventType::* consts, value
     *          is the list of callable
     * @param SocketInterface $socket Socket to unsubscribe. If not provided, than subscriber will be removed
     *          from the list of all socket subscribers.
     *
     * @return void
     * @see AsyncSockets\Event\EventType
     *
     * @api
     */
    public function removeHandler(array $events, SocketInterface $socket = null);

    /**
     * Execute this request
     *
     * @return void
     * @throws \LogicException If you try to call this method when request is active
     *
     * @api
     */
    public function executeRequest();

    /**
     * Stop execution for all registered sockets. Applicable only during request execution
     *
     * @return void
     * @throws \BadMethodCallException When called on non-executing request
     *
     * @api
     */
    public function stopRequest();

    /**
     * Cancel network operation for given socket. Applicable only during request execution
     *
     * @param SocketInterface $socket
     *
     * @return void
     * @throws \BadMethodCallException When called on non-executing request
     *
     * @api
     */
    public function cancelSocketRequest(SocketInterface $socket);

    /**
     * Return flag whether process is in execute stage
     *
     * @return bool
     *
     * @api
     */
    public function isExecuting();

    /**
     * Set decider for limiting running at once requests
     *
     * @param LimitationDecider $decider New decider. If null, then NoLimitationDecider will be used
     *
     * @return void
     * @throws \BadMethodCallException When called on executing request
     * @see NoLimitationDecider
     *
     * @api
     */
    public function setLimitationDecider(LimitationDecider $decider = null);
}
