---------------
Event reference
---------------

To deal with sockets you need to subscribe to events you are interested in. Each event type has
``Event`` object (or one of its children) as the callback argument. If you have `symfony event
dispatcher <http://symfony.com/doc/current/components/event_dispatcher/introduction.html/>`_ component installed ,
the Async Socket Library's ``Event`` object will be inherited from symfony `Event` object.
All events are described in ``EventType`` class.


INITIALIZE
==========

Summary
    The first event sent for each socket and can be
    used for some initializations, for ex. setting
    destination address for client socket and local
    address for server ones.

Constant in ``EventType``
    `INITIALIZE`

**Callback argument**
    ``Event``


CONNECTED
=========

Summary
    Socket has been just connected to server.

Constant in ``EventType``
    `CONNECTED`

**Callback argument**
    ``Event``


ACCEPT
======

Summary
    Applicable only for server sockets, fires each time
    there is a new client, no matter what kind of
    transport is used *tcp*, *udp*, *unix* or something
    else. Client socket can be got from `AcceptEvent`.

Constant in ``EventType``
    `INITIALIZE`

**Callback argument**
    ``AcceptEvent``

.. _reference-events-read:

READ
====

Summary
    New frame has arrived. The ``Frame`` data object can be
    extracted from event object passed to the callback
    function. Applicable only for client sockets.

Constant in ``EventType``
    `READ`

**Callback argument**
    ``ReadEvent``

.. _reference-events-write:

WRITE
=====

Summary
    Socket is ready to write data. New data must be passed
    to socket through event object.

Constant in ``EventType``
    `WRITE`

**Callback argument**
    ``WriteEvent``
    
.. _reference-events-data-alert:

DATA_ALERT
==========

Summary
    Socket is in unmanaged state. Event is fired when
    there are new data in socket, but ``ReadOperation`` is
    not set. This event can be fired several times, the
    typical reaction should be either closing connection
    or setting appropriate ``ReadOperation``. If none of
    this is done, connection will be automatically closed
    and ``UnmanagedSocketException`` will be thrown.

Constant in ``EventType``
    `DATA_ALERT`

**Callback argument**
    ``DataAlertEvent``


DISCONNECTED
============

Summary
    Connection to remote server is now closed. This event
    is not fired when socket hasn't connected.

Constant in ``EventType``
    `DISCONNECTED`

**Callback argument**
    ``Event``


FINALIZE
========

Summary
    Socket lifecycle is complete and one can be removed
    from the executing engine.

Constant in ``EventType``
    `FINALIZE`

**Callback argument**
    ``Event``

.. _reference-events-timeout:

TIMEOUT
=======

Summary
    Socket failed to connect/read/write data during set up
    period of time.

Constant in ``EventType``
    `TIMEOUT`

**Callback argument**
    ``TimeoutEvent``

.. _reference-events-exception:

EXCEPTION
=========

Summary
    Some ``NetworkSocketException`` occurred and detailed
    information can be retrieved from ``SocketExceptionEvent``.

Constant in ``EventType``
    `EXCEPTION`

**Callback argument**
    ``SocketExceptionEvent``

