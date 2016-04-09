===============
Processing data
===============

The :ref:`socket lifecycle <diagram-socket-lifecycle>` is managed via operations.

Every action on the socket is described by an operation. Operations are implementations of ``OperationInterface``. Each
operation is an object, containing concrete data, required for processing. There are 5 available operations:

 * ``ReadOperation``
 * ``WriteOperation``
 * ``SslHandshakeOperation``
 * ``DelayedOperation``
 * ``NullOperation``

.. note::
   For now there is no possibility to add custom operation into library.

ReadOperation
=============

You can read data from socket using ``ReadOperation``.

.. code-block:: php
   :linenos:

   use AsyncSockets\Operation\ReadOperation;

   $operation = new ReadOperation();

This code will create read operation telling the executing engine to handle reading data from the socket. By default
every read operation will immediately call event handler with the data received from socket.

.. note::
   Be ready to process empty data, if you are using ``ReadOperation`` without constructor arguments.

The default behaviour can be easily changed using its constructor argument accepting instance of
``FramePickerInterface``:

.. code-block:: php
   :linenos:

   use AsyncSockets\Operation\ReadOperation;
   use AsyncSockets\Frame\FixedLengthFramePicker;

   $operation = new ReadOperation(new FixedLengthFramePicker(50));

This code will emit :ref:`read event <reference-events-read>` when frame will be finished,
i.e. in this case the 50 bytes of response will be received.
See detailed explanations about :doc:`frame pickers <how_to_work_with_frames>`.

If given frame can not be received, the :ref:`exception event <reference-events-exception>`
is dispatched with ``FrameException`` object inside event argument.

If the remote site does not send any data within chosen period of time,
the :ref:`timeout event <reference-events-timeout>` will be dispatched.

WriteOperation
==============

The ``WriteOperation`` allows to send data to remote side. The data must be *string*. Each write operation
will either send the whole given string without emitting any event or fail with some exception.
The ``WriteOperation`` dispatches :ref:`write event <reference-events-write>` *before* sending the data.

.. code-block:: php
   :linenos:

   use AsyncSockets\Operation\WriteOperation;

   $executor->socketBag()->addSocket(
        $client,
        new WriteOperation("GET / HTTP/1.1\nHost: example.com\n\n"),
        [
            RequestExecutorInterface::META_ADDRESS => 'tls://example.com:443',
        ]
   );

The example above will send the simplest GET request to example.com. If the remote site does not
accept any data within chosen period of time, the :ref:`timeout event <reference-events-timeout>` will be dispatched.

SslHandshakeOperation
=====================

Normally when you intend to establish secured connection with remote host you use address like *tls://example.com:443*
and it works perfect. With one great disadvantage - connection will be done synchronously even if you have switched
socket into non-blocking mode. This happens because of SSL handshake procedure required for successful data exchange.

The ``SslHandshakeOperation`` allows to process the handshake asynchronously leaving the CPU time for some useful work.

Supposing you have request executor instance and socket created, you can connect to remote server asynchronously:

.. code-block:: php
   :linenos:

   use AsyncSockets\Operation\SslHandshakeOperation;

   $executor->socketBag()->addSocket(
       $socket,
       new SslHandshakeOperation(
           new WriteOperation("GET / HTTP/1.1\nHost: example.com\n\n")
       ),
       [
           RequestExecutorInterface::META_ADDRESS => 'tcp://example.com:443',
       ]
   );

The ``SslHandshakeOperation``'s constructor accept two arguments:

  * the operation to execute after the socket has connected;
  * the cipher to use for SSL connection, one of php constant `STREAM_CRYPTO_METHOD_*_CLIENT` for client sockets.

If the second parameter is omitted, the default value `STREAM_CRYPTO_METHOD_TLS_CLIENT` will be used.

If connection can not be established, the :ref:`exception event <reference-events-exception>`
is dispatched with ``SslHandshakeException`` object inside event argument.

.. warning::
   Do not use ``SslHandshakeOperation`` more than once for any socket request as the second call will fail
   with ``SslHandshakeException``.


DelayedOperation
================

The ``DelayedOperation`` allows to postpone operation to some future time determined by a callback function.
The callback function must answer the question *"Is an operation is still pending?"* and return *true* if socket
is waiting for something and *false* when it is ready to proceed. The function is executed each time there is
some *other* socket in the engine to process.

This feature is useful when a socket is waiting data from another one.

The constructor of ``DelayedOperation`` accepts three arguments:

  * the operation to execute after delay is finished;
  * the callback function;
  * optional arguments to pass into callback function.

The callback function prototype must be the following:

.. code-block:: php

   bool function(SocketInterface $socket, RequestExecutorInterface $executor, ...$arguments)

.. warning::
   The callback function is executed only when there is at least one socket except waiting one
   and there is some activity on the second socket. If these
   conditions are not met, the operation on the waiting socket will never finish.

NullOperation
=============

The ``NullOperation`` is a special type of operation which is automatically set for socket, if the
next operation has not been defined in :ref:`read event <reference-events-read>`
or :ref:`write event <reference-events-write>`. This operation does not perform any action and has different meanings
for persistent socket and non-persistent ones.

For non-persistent sockets ``NullOperation`` is considered as the end of request and the engine closes the connection.

For persistent sockets the situation significantly changes since persistent sockets
keep connection all the time. If there are new data to read and ``NullOperation``
is set for the socket, the system dispatches :ref:`data alert event <reference-events-data-alert>`.
In the response to the event you can set the appropriate read
operation and receive the data or close the connection.

.. warning::
   If you don't do anything the connection will be automatically closed by the engine
   after some unsuccessful event calls and ``UnmanagedSocketException`` will be thrown.


