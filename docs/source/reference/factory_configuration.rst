------------------------------------------
AsyncSocketFactory configuration reference
------------------------------------------

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

The list of available options:

+----------------------+-----------+-------------------------------+---------------------------------------------------+
| Key in options array | Data type | Default value                 | Description                                       |
+----------------------+-----------+-------------------------------+---------------------------------------------------+
| connectTimeout       | double    | from *socket_default_timeout* | Default value for execution engine to wait        |
|                      |           | php.ini directive             | connection establishment before considering it as |
|                      |           |                               | timed out.                                        |
+----------------------+-----------+-------------------------------+---------------------------------------------------+
| ioTimeout            | double    | from *socket_default_timeout* | Default value for execution engine to wait some   |
|                      |           | php.ini directive             | I/O activity before considering connection as     |
|                      |           |                               | timed out.                                        |
+----------------------+-----------+-------------------------------+---------------------------------------------------+
| preferredEngines     | string[]  | ['libevent', 'native']        | Preferred order of execution engines to try to    |
|                      |           |                               | create by ``createRequestExecutor()`` method from |
|                      |           |                               | ``AsyncSocketFactory``. Only *native* and         |
|                      |           |                               | *libevent* values are possible inside the array.  |
+----------------------+-----------+-------------------------------+---------------------------------------------------+

.. note::
   Too low timeout values may result in frequent timeouts on sockets.

.. warning::
   Incorrect configuration for *preferredEngines* option will lead to `InvalidArgumentException` is thrown when
   you create the Request executor.
