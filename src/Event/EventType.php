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
     * Socket is about to open. Event object will be given in argument.
     * You should call setSocketMetaData method and fill RequestExecutorInterface::META_ADDRESS with value
     * You can omit handling of this event, if you set RequestExecutorInterface::META_ADDRESS in socket metadata.
     * You can also call open method manually on the socket
     *
     * @see Event
     * @see RequestExecutorInterface::META_ADDRESS
     * @see RequestExecutorInterface::setSocketMetaData
     * @see SocketInterface::open
     */
    const OPEN = 'socket.event.open';

    /**
     * Socket has connected to server. Event object will be given in argument.
     * No special action required here. Do NOT try to read or write data at this point
     *
     * @see Event
     */
    const CONNECTED = 'socket.event.connected';

    /**
     * Socket data can be read. IoEvent object will be given in argument.
     *
     * @see IoEvent
     */
    const READ = 'socket.event.read';

    /**
     * Socket data can be written. IoEvent object will be given in argument.
     *
     * @see IoEvent
     */
    const WRITE = 'socket.event.write';

    /**
     * Socket has disconnected. Event object will be given in argument.
     *
     * @see Event
     */
    const DISCONNECTED = 'socket.event.disconnected';

    /**
     * Connect / read / write operation timeout for socket. Event object will be given in argument.
     *
     * @see Event
     */
    const TIMEOUT = 'socket.event.timeout';

    /**
     * Exception occurred during another event. SocketExceptionEvent will be given in argument.
     *
     * @see SocketExceptionEvent
     */
    const EXCEPTION = 'socket.event.exception';
}
