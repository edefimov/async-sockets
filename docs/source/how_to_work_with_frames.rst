=======================
How to work with frames
=======================

One of the key features of Async Socket Library is the determination of frame boundaries
according to user-provided provided information.

------------
FramePickers
------------

`FramePickers` are the special objects implementing ``FramePickerInterface`` and designed to split incoming
data into :ref:`frames <working-with-frames-frames>`. FramePickers are passed into ``ReadOperation`` as the
first argument of its constructor.

.. code-block:: php
   :linenos:

   use AsyncSockets\Frame\FixedLengthFramePicker;

   $picker    = new ...;
   $operation = new ReadOperation($picker);

There are some implementations available out of the box.

  * FixedLengthFramePicker
  * MarkerFramePicker
  * RawFramePicker
  * EmptyFramePicker

.. warning::
   `FramePickers`' instances are not reusable. Once you've set it into an operation, you can not reuse them
   in another one. Moreover you can not reuse the `FramePicker` after
   receiving :ref:`read event <reference-events-read>`. Create new instance each time instead.

Each `FramePicker` describes some rules that data should match before it can create a frame. If rule can not be
met a ``FrameException`` is thrown in :ref:`exception event <reference-events-exception>`.

The core idea of using `FramePickers` is that client code is aware of data structure it intends to receive
from remote side.

FixedLengthFramePicker
======================

The ``FixedLengthFramePicker`` allows to receive frame of well-known size. The only argument of its constructor
accepts length of frame in bytes.

.. code-block:: php
   :linenos:

   use AsyncSockets\Frame\FixedLengthFramePicker;

   $operation = new ReadOperation(
       new FixedLengthFramePicker(255)
   );

By setting this operation the :ref:`read event <reference-events-read>` will fired only after loading 255 bytes from
remote side.

.. note::
   Actually more data from network can be collected, but event will be fired with exactly given bytes of data.


MarkerFramePicker
=================

If data have variable length, but there are well-known ending and beginning of data (or at least ending) it is
possible to use ``MarkerFramePicker``. It cuts the data between given start marker and end marker, including markers
themselves.

Example usages:

.. code-block:: php
   :linenos:

   use AsyncSockets\Frame\MarkerFramePicker;

   $picker = new MarkerFramePicker('HTTP', "\r\n\r\n"); // return HTTP headers

   $picker = new MarkerFramePicker(null, "\x00"); // reads everything until 0-byte including 0-byte itself

   $picker = new MarkerFramePicker("\x00", "\x00"); // start and end marker can be the same

   $picker = new MarkerFramePicker('<start>', '</START>', true); // returns everything between <start> and </START>
                                                                // case-insensitive compare

.. warning::
   When you use a ``MarkerFramePicker`` and there are some data before the start marker
   passed into `FramePicker`, all these data will be lost. Suppose you have such incoming data:

   .. graphviz:: graph/frames_losing_data.dot
      :caption:

   and such a `FramePicker` used for the first read operation:

   .. code-block:: php
      :linenos:

      $picker = new MarkerFramePicker("X", "X");

   Since it is the first read operation, the data *AAA* will be lost.


RawFramePicker
==============

This kind of `FramePicker` is used by default in ``ReadOperation`` if no other object is provided. With
``RawFramePicker`` the :ref:`read event <reference-events-read>` will be dispatched each time the socket
read data.

.. note::
   Be ready to process even an empty string using this `FramePicker`.

EmptyFramePicker
================

This `FramePicker` does not really read anything and the empty string is the always data for this frame. This
frame has special meaning in SSL context for persistent socket - if there are some data in socket buffer which
can not be treated as a frame, the `FramePicker` can clean it and stop the receiving of
:ref:`data alert event <reference-events-data-alert>`. This kind of garbage collection can be done automatically by
decorating your event handler into ``SslDataFlushEventHandler``.

.. _working-with-frames-frames:

------
Frames
------

After ``FramePicker`` has finished reading data it can create a `Frame`. Frames are a piece of data collected
by current read operation. Each frame implements ``FrameInterface`` providing methods for getting the data
and remote host address these data belongs to.

.. code-block:: php
   :linenos:

   public function onRead(ReadEvent $event)
   {
       $remoteAddress = $event->getFrame()->getRemoteAddress();
       echo "Received data from {$remoteAddress}: \n\n" . $event->getFrame()->getData();
   }

An alternative way of receiving data from the frame is casting an object into a string:

.. code-block:: php
   :linenos:

   public function onRead(ReadEvent $event)
   {
       $remoteAddress = $event->getFrame()->getRemoteAddress();
       echo "Received data from {$remoteAddress}: \n\n {$event->getFrame()}";
   }

Out of the box 3 implementations of Frames are available:

  * ``Frame`` - default implementation for creating finished piece of data;
  * ``PartialFrame`` - an implementation showing that data are incomplete. You will never receive it in read event,
    but if you intend to create a custom ``FramePicker``, you should use this type of frame
    as a result of ``createFrame()`` method from ``FramePickerInterface`` when the system calls it;
  * ``EmptyFrame`` - a frame that always casted into an empty string. This object is returned by ``EmptyFramePicker``.
