=================
Limitation solver
=================

The LimitationSolver is the component allows to restrict amount of executing requests at certain period of time.

.. _component-limitation-solver-writing-custom-solver:

Writing custom limitation solver
================================
Custom limitation solver can be written by implementing ``LimitationSolverInterface``. If you need an access to socket
events from the solver, just implement ``EventHandlerInterface`` in addition to the first one.
