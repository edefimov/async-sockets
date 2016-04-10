==========================================
AsyncSocketFactory configuration reference
==========================================

The ``AsyncSocketFactory`` can be configured using non-standard values. To pass these value into the factory use
``Configuration`` object:

.. code-block:: php
   :linenos:

   use AsyncSockets\Socket\AsyncSocketFactory;
   use AsyncSockets\Configuration\Configuration;

   $options       = ...; // array with options to set
   $configuration = new Configuration($options);
   $factory  = new AsyncSocketFactory($configuration);

You should retrieve options from some source and pass it as key-value array into ``Configuration`` object.

---------------
List of options
---------------

connectTimeout
==============

Data type:
   double

Default value:
   from *socket_default_timeout* php.ini directive

Summary:
   Default value for execution engine to wait connection
   establishment before considering it as timed out.

ioTimeout
=========

Data type:
   double

Default value:
   from *socket_default_timeout* php.ini directive

Summary:
   Default value for execution engine to wait some
   I/O activity before considering connection as timed out.

.. note::
   Too low timeout values may result in frequent timeouts on sockets.


preferredEngines
================

Data type:
   string[]

Default value:
   ['libevent', 'native']

Summary:
   Preferred order of execution engines to try to create by ``createRequestExecutor()`` method from
   ``AsyncSocketFactory``. Only *native* and *libevent* values are possible inside the array.

   .. warning::
      Incorrect configuration for *preferredEngines* option will lead to `InvalidArgumentException` is thrown when
      you create the Request executor.

Details:
   There are two possible implementations of executing engine - `native` and `libevent`. The `libevent` one
   requires libevent_ extension installed, whereas a `native` one can work without any additional requirements.
   See the comparative table below.

.. _libevent: https://pecl.php.net/package/libevent

   +--------------+--------------------------------------------------+
   | Engine       | Pros and cons                                    |
   +--------------+--------------------------------------------------+
   | `native`     | #. Works without any additional requirements.    |
   |              | #. Supports persistent connections               |
   |              | #. By default supports up to 1024 concurrent     |
   |              |    connections and requires PHP recompilation    |
   |              |    to increase this number.                      |
   +--------------+--------------------------------------------------+
   | `libevent`   | #. Requires libevent_ extension                  |
   |              | #. All versions prior to 0.1.1 can not process   |
   |              |    persistent connections and fails with         |
   |              |    "fd argument must be either valid PHP         |
   |              |    stream or valid PHP socket resource"          |
   |              |    warning.                                      |
   |              | #. Version 0.1.1 is available only from          |
   |              |    sources.                                      |
   |              | #. Process more than 1024 concurrent             |
   |              |    connections.                                  |
   +--------------+--------------------------------------------------+
