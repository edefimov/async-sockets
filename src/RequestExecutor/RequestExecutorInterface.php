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

/**
 * Interface RequestExecutorInterface
 *
 * @api
 */
interface RequestExecutorInterface
{
    /**
     * Special timeout value, which means, that socket will wait forever. Use with server sockets
     */
    const WAIT_FOREVER = null;

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
     * Amount of bytes sent by this socket. Read-only value
     */
    const META_BYTES_SENT = 'bytesSent';

    /**
     * Amount of bytes received by this socket. Read-only value
     */
    const META_BYTES_RECEIVED = 'bytesReceived';

    /**
     * Data receiving speed in bytes per second. Read-only value
     */
    const META_RECEIVE_SPEED = 'receiveSpeed';


    /**
     * Data sending speed in bytes per second. Read-only value
     */
    const META_SEND_SPEED = 'sendSpeed';

    /**
     * Any user-defined value, not used internally at all.
     */
    const META_USER_CONTEXT = 'userContext';

    /**
     * Address to connect for socket. Has meaning only while execute process hasn't started. If this key is
     * specified, you can omit processing of EventType::INITIALIZE event
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
     * Minimum receive speed for this socket in bytes per second
     */
    const META_MIN_RECEIVE_SPEED = 'minReceiveSpeed';

    /**
     * Time after which a receive request will be treated as too slow and aborted, in seconds
     */
    const META_MIN_RECEIVE_SPEED_DURATION = 'minReceiveSpeedDuration';

    /**
     * Minimum send speed for this socket in bytes per second
     */
    const META_MIN_SEND_SPEED = 'minSendSpeed';

    /**
     * Time after which a send request will be treated as too slow and aborted, in seconds
     */
    const META_MIN_SEND_SPEED_DURATION = 'minSendSpeedDuration';

    /**
     * Keeps connection opened after processing request
     */
    const META_KEEP_ALIVE = 'keepAlive';

    /**
     * Return socket bag, associated with this executor
     *
     * @return SocketBagInterface
     */
    public function socketBag();

    /**
     * Set event handler for all events
     *
     * @param EventHandlerInterface $handler Event invocation handler
     *
     * @return void
     */
    public function withEventHandler(EventHandlerInterface $handler);

    /**
     * Set solver for limiting running at once requests. You can additionally implement EventHandlerInterface
     * on your LimitationDecider to receive events from request executor
     *
     * @param LimitationSolverInterface $solver New solver
     *
     * @return void
     * @throws \BadMethodCallException When called on executing request
     * @see NoLimitationDecider
     * @see EventInvocationHandlerInterface
     *
     * @api
     */
    public function withLimitationSolver(LimitationSolverInterface $solver);

    /**
     * Execute this request
     *
     * @param ExecutionContext|null $context Context with custom data for processing request
     *
     * @return void
     * @api
     */
    public function executeRequest(ExecutionContext $context = null);

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
     * Return flag whether process is in execute stage
     *
     * @return bool
     *
     * @api
     */
    public function isExecuting();
}
