==================
Persistent sockets
==================

Persistent sockets allow to reuse opened connection for subsequent requests. For creating a
persistent socket you should pass additional options into ``createSocket()`` method from
``AsyncSocketFactory``:

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

There are two options allow to control persistent socket connections:

    * AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT - a **boolean** flag, must be explicitly set to **true**
      for persistent sockets;
    * AsyncSocketFactory::SOCKET_OPTION_PERSISTENT_KEY - **string**, an optional name to store this connection.
      Changing this value for the same host and port opens multiple connections to the same server.

.. note::
   All persistent sockets must be explicitly closed.

.. warning::
   When you process read or write event and don't set next operation on the persistent socket, the one becomes unmanaged.
   This means next time there will be new network activity, you will receive
   :ref:`data alert event <reference-events-data-alert>`.
