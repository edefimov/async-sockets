---------------
Event reference
---------------

To deal with sockets you need to subscribe to events you are interested in. Each event type has
``Event`` object (or one of its children) as the callback argument. If you have symfony event
dispatcher component installed , the async socket library ``Event`` object will be inherited from symfony `Event` object.
All events are described in ``EventType`` class.

+--------------------------------+----------------------------+--------------------------------------------------------+
| Constant in  ``EventType``     | Event argument in callback | Description                                            |
+--------------------------------+----------------------------+--------------------------------------------------------+
| INITIALIZE                     | ``Event``                  | The first event sent for each socket and can be used   |
|                                |                            | for some initializations, for ex. setting destination  |
|                                |                            | address for client socket and local address for server |
|                                |                            | ones.                                                  |
+--------------------------------+----------------------------+--------------------------------------------------------+
| CONNECTED                      | ``Event``                  | Socket has been just connected to server.              |
+--------------------------------+----------------------------+--------------------------------------------------------+
| ACCEPT                         | ``AcceptEvent``            | Applicable only for server sockets, fires each time    |
|                                |                            | there is a new client, no matter what kind of          |
|                                |                            | transport is used *tcp*, *udp*, *unix* or something    |
|                                |                            | else. Client socket can be got from `AcceptEvent`      |
+--------------------------------+----------------------------+--------------------------------------------------------+
| READ                           | ``ReadEvent``              | New frame has arrived. The ``Frame`` data object can   |
|                                |                            | be extracted from event object passed to the callback  |
|                                |                            | function. Applicable only for client sockets.          |
+--------------------------------+----------------------------+--------------------------------------------------------+
| WRITE                          | ``WriteEvent``             | Socket is ready to write data. New data must be passed |
|                                |                            | to socket through event object.                        |
+--------------------------------+----------------------------+--------------------------------------------------------+
| DATA_ALERT                     | ``DataAlertEvent``         | Socket is in unmanaged state. Event is fired when      |
|                                |                            | there are new data in socket, but ``ReadOperation`` is |
|                                |                            | not set. This event can be fired several times, the    |
|                                |                            | typical reaction should be either closing connection   |
|                                |                            | or setting appropriate ``ReadOperation``. If none of   |
|                                |                            | this is done, connection will be automatically closed  |
|                                |                            | and ``UnmanagedSocketException`` will be thrown.       |
+--------------------------------+----------------------------+--------------------------------------------------------+
| DISCONNECTED                   | ``Event``                  | Connection to remote server is now closed. This event  |
|                                |                            | is not be fired when socket hasn't connected.          |
+--------------------------------+----------------------------+--------------------------------------------------------+
| FINALIZE                       | ``Event``                  | Socket lifecycle is complete and one can be removed    |
|                                |                            | from the executing engine.                             |
+--------------------------------+----------------------------+--------------------------------------------------------+
| TIMEOUT                        | ``TimeoutEvent``           | Socket failed to connect/read/write data during set up |
|                                |                            | period of time.                                        |
+--------------------------------+----------------------------+--------------------------------------------------------+
| EXCEPTION                      | ``TimeoutEvent``           | Some ``NetworkSocketException`` occurred detailed      |
|                                |                            | information can be retrieved from                      |
|                                |                            | ``SocketExceptionEvent``                               |
+--------------------------------+----------------------------+--------------------------------------------------------+

