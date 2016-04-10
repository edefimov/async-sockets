=================
Limitation solver
=================

The LimitationSolver is the component which allows to restrict amount of executing requests at certain period of time.

.. _component-limitation-solver-writing-custom-solver:

Writing custom limitation solver
================================
Custom limitation solver can be written by implementing ``LimitationSolverInterface``.

The ``LimitationSolverInterface`` contains 3 methods:

  * ``initialize()`` is called before engine execution loop is started;
  * ``finalize()`` is called after engine execution loop is finished;
  * ``decide()`` is called each time the engine needs to make some decision about the socket.

The prototype of decide method looks like:

.. code-block:: php

   public function decide(RequestExecutorInterface $executor, SocketInterface $socket, $totalSockets);

The ``decide`` method should return a hint for engine what to do with certain given socket. The possible decisions are:

  * DECISION_OK - schedule request for given socket;
  * DECISION_PROCESS_SCHEDULED - the engine has enough scheduled sockets and should process them before taking new ones;
  * DECISION_SKIP_CURRENT - this certain socket should not be processed right now.

If you need an access to socket events from the solver,
just implement ``EventHandlerInterface`` in addition to the ``LimitationSolverInterface`` one.

The simple implementation of the ``LimitationSolverInterface`` is ``ConstantLimitationSolver``:

.. code-block:: php
   :linenos:

   class ConstantLimitationSolver implements LimitationSolverInterface, EventHandlerInterface
   {
       private $limit;
       private $activeRequests;

       public function __construct($limit)
       {
           $this->limit = $limit;
       }

       public function initialize(RequestExecutorInterface $executor)
       {
           $this->activeRequests = 0;
       }

       public function decide(RequestExecutorInterface $executor, SocketInterface $socket, $totalSockets)
       {
           if ($this->activeRequests + 1 <= $this->limit) {
               return self::DECISION_OK;
           } else {
               return self::DECISION_PROCESS_SCHEDULED;
           }
       }

       public function invokeEvent(Event $event)
       {
           switch ($event->getType()) {
               case EventType::INITIALIZE:
                   $this->activeRequests += 1;
                   break;
               case EventType::FINALIZE:
                   $this->activeRequests -= 1;
                   break;
           }
       }

       public function finalize(RequestExecutorInterface $executor)
       {

       }
   }
