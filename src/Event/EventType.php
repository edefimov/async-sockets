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
 
/**
 * Class EventType
 *
 * @api
 */
final class EventType
{
    /**
     * It is the first event, which you receive for socket. Event object will be given as an argument.
     * This is useful for making some initialization work. You should call setSocketMetaData method and fill
     * RequestExecutorInterface::META_ADDRESS with value. You can omit handling of this event, if you
     * set RequestExecutorInterface::META_ADDRESS in socket metadata.
     * You can also call open method manually on the socket
     *
     * @see Event
     * @see RequestExecutorInterface::META_ADDRESS
     * @see SocketBag::setSocketMetaData
     * @see SocketInterface::open
     *
     * @see Event
     */
    const INITIALIZE = 'socket.event.initialize';

    /**
     * Socket has connected to server. Event object will be given as an argument.
     * No special action required here. Do NOT try to read or write data at this point
     *
     * @see Event
     */
    const CONNECTED = 'socket.event.connected';

    /**
     * Event is fired by server sockets when new client was connected. AcceptEvent will be given as an argument
     *
     * @see AcceptEvent
     */
    const ACCEPT = 'socket.event.accept';

    /**
     * Socket data has been read into event object. ReadEvent object will be given as an argument. Use event object
     * to get received data
     *
     * @see ReadEvent
     */
    const READ = 'socket.event.read';

    /**
     * Socket data can be written. WriteEvent object will be given as an argument. Use event object
     * for writing data
     *
     * @see WriteEvent
     */
    const WRITE = 'socket.event.write';

    /**
     * There are new data in socket, but read operation is not set. In the response to
     * this event you should either close a connection, or set appropriate read operation. If none of
     * these is done, socket will be automatically closed with UnmanagedSocketException thrown.
     *
     * @see IoEvent
     */
    const DATA_ALERT = 'socket.event.data_alert';

    /**
     * Socket has disconnected. Event object will be given as an argument.
     *
     * @see Event
     */
    const DISCONNECTED = 'socket.event.disconnected';

    /**
     * It is the last event, which you receive for socket. This event is useful for cleaning stuff after initialization.
     * You will receive this event even in case of errors in socket processing. Event object will be given
     * as an argument.
     *
     * @see Event
     */
    const FINALIZE = 'socket.event.finalize';

    /**
     * Connect / read / write operation timeout for socket. TimeoutEvent object will be given as an argument.
     *
     * @see TimeoutEvent
     */
    const TIMEOUT = 'socket.event.timeout';

    /**
     * Exception occurred during another event. SocketExceptionEvent will be given as an argument.
     *
     * @see SocketExceptionEvent
     */
    const EXCEPTION = 'socket.event.exception';
}
