============
Socket types
============

Async Socket Library provides two types of sockets - client and server. The client socket can be either persistent or
non-persistent. The recommended way of creating sockets of different types in source code is by using ``AsyncSocketFactory``:

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

The above code will create persistent socket. See :doc:`persistent sockets <persistent_sockets>` to get detailed information.

All available options are now linked with :doc:`persistent sockets <persistent_sockets>`.
