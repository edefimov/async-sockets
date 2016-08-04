====================
The execution engine
====================

******************
AsyncSocketFactory
******************

The ``AsyncSocketFactory`` is an entry point to the Async Socket Library. The factory is used to create sockets
and request executors. You can use direct instantiation for this object:

.. code-block:: php
   :linenos:

   use AsyncSockets\Socket\AsyncSocketFactory;

   $factory = new AsyncSocketFactory();

Factory can be configured using ``Configuration`` object. The above code is the shortcut for configuring factory with
default values:

.. code-block:: php
   :linenos:

   use AsyncSockets\Socket\AsyncSocketFactory;
   use AsyncSockets\Configuration\Configuration;

   $configuration = new Configuration(
       [
           'connectTimeout'   => ini_get('default_socket_timeout'),
           'ioTimeout'        => ini_get('default_socket_timeout'),
           'preferredEngines' => ['libevent', 'native'],
       ]
   );

   $factory  = new AsyncSocketFactory($configuration);

.. note::
   To see all configuration options see :doc:`Options reference <reference/factory_configuration>`

****************
Request executor
****************

Request executor is a primary execution engine for all socket requests. Each time you need to run a request,
you will use Request executor. Request executors are defined by ``RequestExecutorInterface``
which allows to customize operations processing. There are two different implementations out of the box:
native and libevent. The former is the pure php handler, the latter is based on libevent_ php library.

.. _libevent: https://pecl.php.net/package/libevent

You can receive an instance of ``RequestExecutorInterface`` using the factory:

 .. code-block:: php
    :linenos:

    use AsyncSockets\Socket\AsyncSocketFactory;

    $factory  = new AsyncSocketFactory();
    $executor = $factory->createRequestExecutor();

The purposes of Request Executor are:

    * Providing a bag for adding sockets (See :ref:`setting-up-a-socket` section below);
    * Dispatching events during sockets' lifecycle;
    * Executing request.

A request executor can be set up with global event handler, which will be applied to each added socket, and
with limitation solver - an object restricting amount of executing requests at time.

Global event handler is the implementation of ``EventHandlerInterface``, which will be called for every event on every
added socket. There are four implementations of this interface out of the box:

 * ``CallbackEventHandler`` takes array of callable, indexed by event type. For certain event type a certain
   callable will be invoked. Several callbacks can be defined for one event type;

   .. code-block:: php
      :linenos:

      $handler = new CallbackEventHandler(
                [
                    EventType::INITIALIZE => [$this, 'logEvent'],
                    EventType::WRITE      => [ [$this, 'logEvent'], [$this, 'onWrite'] ],
                    EventType::READ       => [ [$this, 'logEvent'], [$this, 'onRead'] ],
                    EventType::EXCEPTION  => [$this, 'onException'],
                    EventType::TIMEOUT    => [
                        [$this, 'onTimeout'],
                        function () {
                            echo "Timeout occured!\n";
                        }
                    ],
                ]
      );
 * ``EventHandlerFromSymfonyEventDispatcher`` dispatches all socket event to symfony EventDispatcher_;
 * ``EventMultiHandler`` is the composite for ``EventHandlerInterface`` implementations;
 * ``RemoveFinishedSocketsEventHandler`` decorator for any implementation of ``EventHandlerInterface`` which
   automatically removes completed sockets from ``RequestExecutor``. Recommended to use for accepted clients
   from server sockets.

.. note::
   You can register several global event handlers using ``withEventHandler`` method of ``RequestExecutorInterface``.

.. _EventDispatcher: http://symfony.com/doc/current/components/event_dispatcher/introduction.html

The limitation solver is the component restricting amount of executed at once requests. Out of the box two strategies
are available:

 * ``NoLimitationSolver`` doesn't restrict anything, it is a default one;
 * ``ConstantLimitationSolver`` restricts amount of running requests to given number.

.. note::
   You can write custom limitation solver, see :ref:`Custom limitation solver <component-limitation-solver-writing-custom-solver>`

To set up event handler or limitation solver use the following code:

.. code-block:: php
   :linenos:

   $executor->withEventHandler(
        new CallbackEventHandler(
            [
                EventType::INITIALIZE => [$this, 'onInitialize'],
                EventType::WRITE      => [$this, 'onWrite'],
                ....
            ]
        )
   );

   $executor->withLimitationSolver(new ConstantLimitationSolver(20));


Socket lifecycle
================

During request socket pass through lifecycle shown in the figure below.

.. _diagram-socket-lifecycle:

.. graphviz:: graph/socket_lifecycle.dot
   :caption: Socket lifecycle

Each state except *added* and *removed* calls event handler with some information about occurred event.


.. _setting-up-a-socket:

Setting up a socket
===================

Socket can be added into execution engine using ``socketBag()`` method from ``RequestExecutorInterface``. It returns
object of class ``SocketBagInterface`` allows to manage sockets. Socket bag is a container for all sockets processed
by the engine. Every socket can have it's own event handler and options.

You can use the following code to add socket into `RequestExecutor`:

.. code-block:: php
   :linenos:

   $executor->socketBag()->addSocket(
       $socket,
       new WriteOperation('some data'),
       [
           RequestExecutorInterface::META_ADDRESS            => 'tls://example.com:443',
           RequestExecutorInterface::META_CONNECTION_TIMEOUT => 30,
           RequestExecutorInterface::META_IO_TIMEOUT         => 5,
       ],
       $handler
   );

Method ``addSocket()`` accepts four arguments: socket, operation, metadata and event handler.
Socket is the object, created by ``AsyncSocketFactory`` or received by `AcceptEvent`.
:doc:`Metadata <reference/socket_metadata>` is a key-value array with settings for this socket.
Event handler is an implementation of ``EventHandlerInterface``, which will be invoked only for this socket.

Once you set up all sockets, you can execute the request:

.. code-block:: php
   :linenos:

   $executor->executeRequest();

.. warning::
   You should not create nested `RequestExecutor` during request processing.
