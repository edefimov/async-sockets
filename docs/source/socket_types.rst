============
Socket types
============

Async socket library provides two types of sockets - client and server. The client socket can be either persistent or
non-persistent. The recommended way to create sockets of different types in source code is by using ``AsyncSocketFactory``:

.. code-block:: php
   :linenos:

   use AsyncSockets\Socket\AsyncSocketFactory;

   $factory = new AsyncSocketFactory();

   $client  = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
   $server  = $factory->createSocket(AsyncSocketFactory::SOCKET_SERVER);

You may pass additional options via second argument of ``createSocket()``:

.. code-block:: php
   :linenos:

   use AsyncSockets\Socket\AsyncSocketFactory;

   $factory = new AsyncSocketFactory();
   $client  = $factory->createSocket(
       AsyncSocketFactory::SOCKET_CLIENT,
       [
           AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT => true
       ]
   );

The above code will create persistent socket.

For now two options are available for ``createSocket()`` method:

    * AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT
        **boolean**, flag whether this socket must be persistent, default value *false*

    * AsyncSocketFactory::SOCKET_OPTION_PERSISTENT_KEY
        **string**, key for storing socket resource, applicable only if *AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT*
        is *true*. Using various values for this key allows to establish multiple persistent
        connections to the same host and port. Default value is empty string.
