-------------------------
Socket metadata reference
-------------------------

Metadata are settings for all operations on given socket. Supported keys are defined in ``RequestExecutorInterface``.

You can pass these options either during adding a socket into engine's bag:

.. code-block:: php
   :linenos:

   use AsyncSockets\Operation\WriteOperation;

   $executor->socketBag()->addSocket(
       $client,
       new WriteOperation(),
       [
           RequestExecutorInterface::META_ADDRESS            => 'tls://example.com:443',
           RequestExecutorInterface::META_CONNECTION_TIMEOUT => 30,
           RequestExecutorInterface::META_IO_TIMEOUT         => 5,
       ]
   );

or you can set these settings later via ``setSocketMetaData()`` method.


META_ADDRESS
============

Data type:
    string

Read-only:
    no

Summary:
    Remote address in form scheme://target, destination address
    for client socket and local address for server ones.
    This value is required for manually created sockets and
    can be ignored for accepted ones.



META_CONNECTION_TIMEOUT
=======================

Data type:
    integer

Read-only:
    no

Summary:
    Value in seconds, if connection is not established during
    this time, socket will be closed automatically and
    *TIMEOUT* event will be fired. If value is omitted then
    value from ``Configuration`` will be used.


META_IO_TIMEOUT
===============

Data type:
    double

Read-only:
    no

Summary:
    Value in seconds, if no data are sent/received during this
    time, socket will be closed automatically and
    *TIMEOUT* event will be fired. If value is omitted then
    value from ``Configuration`` will be used.


META_USER_CONTEXT
=================

Data type:
    mixed

Read-only:
    no

Summary:
    Arbitrary user data. This field is not used anyhow by the engine.


META_SOCKET_STREAM_CONTEXT
==========================

Data type:
    * array
    * resource
    * null

Read-only:
    no

Summary:
    Settings to set up in socket resource.

    If value is a resource it must be a valid stream context
    created by stream_context_create_ PHP function.

    If value is array, it must contain two nested keys:
    *options* and *params*, which will be passed into
    stream_context_create_ corresponding parameters.

    If value is null, the default context returned by
    stream_context_get_default_ PHP function will be used.


META_REQUEST_COMPLETE
=====================

Data type:
    bool

Read-only:
    yes

Summary:
    Value indicating that execution for this request
    is finished. Socket with this flag set can be safely removed
    from engine's socket bag.


META_CONNECTION_START_TIME
==========================

Data type:
    * double
    * null

Read-only:
    yes

Summary:
    Timestamp value, int part is seconds and float is
    microseconds, indicates when connection process is started.

    If connection has not started yet, the value is null.


META_CONNECTION_FINISH_TIME
===========================

Data type:
    * double
    * null

Read-only:
    yes

Summary:
    Timestamp value, int part is seconds and float is
    microseconds, indicates when connection process was finished.

    If connection has not finished yet, the value is null.


META_LAST_IO_START_TIME
=======================

Data type:
    * double
    * null

Read-only:
    yes

Summary:
    Timestamp value, int part is seconds and float is
    microseconds, indicates when last I/O operation has started.

    If there were no I/O operation, the value would be null.


META_BYTES_SENT
===============

Data type:
    * int

Read-only:
    yes

Summary:
    Amount of bytes sent via this socket.

META_BYTES_RECEIVED
===================

Data type:
    * int

Read-only:
    yes

Summary:
    Amount of bytes received via this socket.

.. note::
   This value counts data handled by stream wrapper, i.e. decompressed and decrypted.

.. _stream_context_create: http://php.net/manual/en/function.stream-context-create.php
.. _stream_context_get_default: http://php.net/manual/en/function.stream-context-get-default.php
