-------------------------
Socket metadata reference
-------------------------

Metadata are settings for all operations on given socket. Supported keys are defined in ``RequestExecutorInterface``.

The list of supported keys:

+-------------------------------------------+-----------+--------------------------------------------------------------+
| Constant in  ``RequestExecutorInterface`` | Data type | Description                                                  |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_ADDRESS                              | string    | Remote address in form scheme://target, destination address  |
|                                           |           | for client socket and local address for server sockets.      |
|                                           |           | This value is required for manually created sockets and      |
|                                           |           | can be ignored for accepted ones.                            |
|                                           |           |                                                              |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_CONNECTION_TIMEOUT                   | integer   | Value in seconds, if connection is not established during    |
|                                           |           | this time, socket will be closed automatically and           |
|                                           |           | *TIMEOUT* event will be fired. If value is omitted then      |
|                                           |           | value from socket ``Configuration`` will be used             |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_IO_TIMEOUT                           | double    | Value in seconds, if no data are sent/received during this   |
|                                           |           | time, socket will be closed automatically and                |
|                                           |           | *TIMEOUT* event will be fired. If value is omitted then      |
|                                           |           | value from socket ``Configuration`` will be used             |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_USER_CONTEXT                         | mixed     | Arbitrary user data. This field is not used anyhow by the    |
|                                           |           | engine.                                                      |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_SOCKET_STREAM_CONTEXT                | array     | If value is a resource it must be a valid stream context     |
|                                           |           | created by stream_context_create_ PHP function.              |
|                                           | resource  |                                                              |
|                                           |           | If value is array, it must contain two nested keys:          |
|                                           | null      | *options* and *params*, which will be passed into            |
|                                           |           | stream_context_create_ corresponding parameters              |
|                                           |           |                                                              |
|                                           |           | If value is null, the default context returned by            |
|                                           |           | stream_context_get_default_ PHP function will be used        |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_REQUEST_COMPLETE                     | bool      | Read-only value indicating that execution for this request   |
|                                           |           | is finished. Socket with this flag set can be safely removed |
|                                           |           | from engine's socket bag                                     |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_CONNECTION_START_TIME                | double    | Read-only value, int part is seconds and float is            |
|                                           | null      | microseconds, indicates when connection process is started.  |
|                                           |           |                                                              |
|                                           |           | If connection is has not started yet, the value is null.     |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_CONNECTION_FINISH_TIME               | double    | Read-only value, int part is seconds and float is            |
|                                           | null      | microseconds, indicates when connection process was finished.|
|                                           |           |                                                              |
|                                           |           | If connection is has not finished yet, the value is null.    |
+-------------------------------------------+-----------+--------------------------------------------------------------+
| META_LAST_IO_START_TIME                   | double    | Read-only value, int part is seconds and float is            |
|                                           | null      | microseconds, indicates when last I/O operation has started. |
|                                           |           |                                                              |
|                                           |           | If there were no I/O operation, the value would be null.     |
+-------------------------------------------+-----------+--------------------------------------------------------------+

.. _stream_context_create: http://php.net/manual/en/function.stream-context-create.php
.. _stream_context_get_default: http://php.net/manual/en/function.stream-context-get-default.php
