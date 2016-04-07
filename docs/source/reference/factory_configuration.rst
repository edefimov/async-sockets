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

Summary:
   Default value for execution engine to wait connection
   establishment before considering it as timed out.

Data type:
   double

Default value:
   from *socket_default_timeout* php.ini directive

ioTimeout
=========

Summary:
   Default value for execution engine to wait some
   I/O activity before considering connection as timed out.

Data type:
   double

Default value:
   from *socket_default_timeout* php.ini directive

.. note::
   Too low timeout values may result in frequent timeouts on sockets.


preferredEngines
================

Summary:
   Preferred order of execution engines to try to create by ``createRequestExecutor()`` method from
   ``AsyncSocketFactory``. Only *native* and *libevent* values are possible inside the array.

Data type:
   string[]

Default value:
   ['libevent', 'native']

.. warning::
   Incorrect configuration for *preferredEngines* option will lead to `InvalidArgumentException` is thrown when
   you create the Request executor.
