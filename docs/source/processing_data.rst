===============
Processing data
===============

The :ref:`socket lifecycle <diagram-socket-lifecycle>` is managed via operations.

Operations
==========

Every action on the socket is described by an operation. Operations are implementation of ``OperationInterface``. Each
operation is an object, containing concrete data, required by operation. There are 5 available operations:

 * ``ReadOperation``
 * ``WriteOperation``
 * ``SslHandshakeOperation``
 * ``DelayedOperation``
 * ``NullOperation``

.. note::
   For now there is no possibility to add custom operation into library.

ReadOperation
-------------

You can read data from socket using ``ReadOperation``.

.. code-block:: php
   :linenos:

   $operation = new ReadOperation();

This code will create read operation telling the executing engine to handle reading data from the socket. By default
every read operation will immediately call event handler

