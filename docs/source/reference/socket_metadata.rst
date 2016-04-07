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

Summary:
    Remote address in form scheme://target, destination address
    for client socket and local address for server sockets.
    This value is required for manually created sockets and
    can be ignored for accepted ones.

Read-only:
    no
    
Data type:
    string


META_CONNECTION_TIMEOUT
=======================

Summary:
    Value in seconds, if connection is not established during
    this time, socket will be closed automatically and
    *TIMEOUT* event will be fired. If value is omitted then
    value from socket ``Configuration`` will be used.

Read-only:
    no
    
Data type:
    integer


META_IO_TIMEOUT
===============

Summary:
    Value in seconds, if no data are sent/received during this
    time, socket will be closed automatically and
    *TIMEOUT* event will be fired. If value is omitted then
    value from socket ``Configuration`` will be used.
    
Read-only:
    no
    
Data type:
    double


META_USER_CONTEXT
=================

Summary:
    Arbitrary user data. This field is not used anyhow by the engine.

Read-only:
    no
    
Data type:
    mixed


META_SOCKET_STREAM_CONTEXT
==========================

Summary:
    Settings to set up in socket resource.

    If value is a resource it must be a valid stream context
    created by stream_context_create_ PHP function.

    If value is array, it must contain two nested keys:
    *options* and *params*, which will be passed into
    stream_context_create_ corresponding parameters.

    If value is null, the default context returned by
    stream_context_get_default_ PHP function will be used.

Read-only:
    no

Data type:
    * array
    * resource
    * null


META_REQUEST_COMPLETE
=====================

Summary:
    Value indicating that execution for this request
    is finished. Socket with this flag set can be safely removed
    from engine's socket bag.

Read-only:
    yes

Data type:
    bool


META_CONNECTION_START_TIME
==========================

Summary:
    Timestamp value, int part is seconds and float is
    microseconds, indicates when connection process is started.

    If connection is has not started yet, the value is null.

Read-only:
    yes

Data type:
    * double
    * null


META_CONNECTION_FINISH_TIME
===========================

Summary:
    Timestamp value, int part is seconds and float is
    microseconds, indicates when connection process was finished.

    If connection is has not finished yet, the value is null.

Read-only:
    yes

Data type:
    * double
    * null


META_LAST_IO_START_TIME
=======================

Summary:
    Timestamp value, int part is seconds and float is
    microseconds, indicates when last I/O operation has started.

    If there were no I/O operation, the value would be null.

Read-only:
    yes

Data type:
    * double
    * null

.. _stream_context_create: http://php.net/manual/en/function.stream-context-create.php
.. _stream_context_get_default: http://php.net/manual/en/function.stream-context-get-default.php
