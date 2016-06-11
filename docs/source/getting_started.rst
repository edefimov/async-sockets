===============
Getting started
===============

Installation
------------

The recommended way to install async sockets library is through composer

stable version:

.. code-block:: bash

   $ composer require edefimov/async-sockets:~0.3.0 --prefer-dist|--prefer-source


actual version:

.. code-block:: bash

    $ composer require edefimov/async-sockets:dev-master

Use `--prefer-dist` option in production environment, so as it ignores downloading of test and demo files,
and `--prefer-source` option for development. Development version includes both test and demo files.

Quick start
-----------

 1. Create ``AsyncSocketFactory`` at the point where you want to start request. This object is the entry point to the library:

 .. code-block:: php
    :linenos:

    use AsyncSockets\Socket\AsyncSocketFactory;

    $factory = new AsyncSocketFactory();

 2. Create `RequestExecutor` and proper amount of sockets:

 .. code-block:: php
    :linenos:

    $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
    $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

    $executor = $factory->createRequestExecutor();


 3. Create event handler with events, you are interested in:

  .. code-block:: php
    :linenos:

    use AsyncSockets\RequestExecutor\CallbackEventHandler;

    $handler = new CallbackEventHandler(
        [
            EventType::INITIALIZE   => [$this, 'onInitialize'],
            EventType::CONNECTED    => [$this, 'onConnected'],
            EventType::WRITE        => [$this, 'onWrite'],
            EventType::READ         => [$this, 'onRead'],
            EventType::ACCEPT       => [$this, 'onAccept'],
            EventType::DATA_ALERT   => [$this, 'onDataAlert'],
            EventType::DISCONNECTED => [$this, 'onDisconnected'],
            EventType::FINALIZE     => [$this, 'onFinalize'],
            EventType::EXCEPTION    => [$this, 'onException'],
            EventType::TIMEOUT      => [$this, 'onTimeout'],
        ]
    );


 4. Add sockets into `RequestExecutor`:

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
      ],
      $handler
  );
  $executor->socketBag()->addSocket(
      $anotherClient,
      new WriteOperation(),
      [
          RequestExecutorInterface::META_ADDRESS            => 'tls://example.net:443',
          RequestExecutorInterface::META_CONNECTION_TIMEOUT => 10,
          RequestExecutorInterface::META_IO_TIMEOUT         => 2,
      ],
      $handler
  );


 5. Execute it!

 .. code-block:: php
  :linenos:

  $executor->executeRequest();

The whole example may look like this:

.. code-block:: php
   :linenos:

   namespace Demo;

   use AsyncSockets\Event\Event;
   use AsyncSockets\Event\EventType;
   use AsyncSockets\Event\ReadEvent;
   use AsyncSockets\Event\SocketExceptionEvent;
   use AsyncSockets\Event\WriteEvent;
   use AsyncSockets\Frame\MarkerFramePicker;
   use AsyncSockets\RequestExecutor\CallbackEventHandler;
   use AsyncSockets\RequestExecutor\RequestExecutorInterface;
   use AsyncSockets\Operation\WriteOperation;
   use AsyncSockets\Socket\AsyncSocketFactory;
   use AsyncSockets\Socket\SocketInterface;

   class RequestExecutorExample
   {
       public function run()
       {
           $factory = new AsyncSocketFactory();

           $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
           $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

           $executor->socketBag()->addSocket(
                $client,
                new WriteOperation("GET / HTTP/1.1\nHost: example.com\n\n"),
                [
                    RequestExecutorInterface::META_ADDRESS => 'tls://example.com:443',
                ]
           );

           $executor->socketBag()->addSocket(
               $anotherClient,
               new WriteOperation("GET / HTTP/1.1\nHost: example.net\n\n"),
               [
                   RequestExecutorInterface::META_ADDRESS => 'tls://example.net:443',
               ]
           );

           $executor->withEventHandler(
               new CallbackEventHandler(
                   [
                       EventType::WRITE      => [$this, 'onWrite'],
                       EventType::READ       => [$this, 'onRead'],
                   ]
               )
           );

           $executor->executeRequest();
       }

       public function onWrite(WriteEvent $event)
       {
            $event->nextIsRead(new MarkerFramePicker(null, '</html>', false));
       }

       public function onRead(ReadEvent $event)
        {
            $socket  = $event->getSocket();
            $meta    = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());

            $response = $event->getFrame()->getData();

            echo "<info>{$meta[RequestExecutorInterface::META_ADDRESS]}  read " .
                 number_format(strlen($response), 0, ',', ' ') . ' bytes</info>';
        }
   }

Here you create two sockets, the first will receive the main page from *example.net* and the second will receive
mainpage from *example.com*. You should also inform the execution engine about the first I/O operation on the socket and destination
address. These are minimum settings required for executing any request.


When connection is successfully established, since the ``WriteOperation`` is set, the `onWrite` method
will be called by engine. Within write handler you tell the engine to prepare `read` operation
with `marker` frame boundary detection strategy.

When the data are downloaded and is satisfied by given strategy, the `onRead` handler will be invoked, where you have
access to downloaded data and some additional information about data.

Since in the `onRead` handler you don't ask the engine to prepare another I/O operation, the connection will be automatically
closed for you.
