<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\RequestExecutor\ConstantLimitationSolver;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\LimitationSolverInterface;

/**
 * Class ConstantLimitationSolverTest
 */
class ConstantLimitationSolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Limitation for test
     */
    const TEST_LIMIT = 10;

    /**
     * Decider object
     *
     * @var ConstantLimitationSolver
     */
    private $decider;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->decider = new ConstantLimitationSolver(self::TEST_LIMIT);
    }

    /**
     * testMakingDecision
     *
     * @return void
     */
    public function testExcessCount()
    {
        $mock   = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                        ->getMockForAbstractClass();
        $socket = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
                        ->getMockForAbstractClass();
        /** @var \AsyncSockets\RequestExecutor\RequestExecutorInterface $mock */
        /** @var \AsyncSockets\Socket\SocketInterface $socket */
        $context = new ExecutionContext();
        $this->decider->initialize($mock, $context);

        for ($i = 0; $i <= self::TEST_LIMIT; $i++) {
            $decision = $this->decider->decide($mock, $socket, $context, self::TEST_LIMIT + 1);
            $this->decider->invokeEvent(
                new Event($mock, $socket, null, EventType::INITIALIZE),
                $mock,
                $socket,
                $context
            );
            if ($i < self::TEST_LIMIT) {
                self::assertEquals(
                    LimitationSolverInterface::DECISION_OK,
                    $decision,
                    'Invalid decision in normal case'
                );
            } else {
                self::assertEquals(
                    LimitationSolverInterface::DECISION_PROCESS_SCHEDULED,
                    $decision,
                    'Invalid excessed decision'
                );
            }
        }
        $this->decider->finalize($mock, $context);
    }

    /**
     * testWithRequestComplete
     *
     * @return void
     */
    public function testWithRequestComplete()
    {
        $mock    = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                            ->getMockForAbstractClass();
        $socket  = $this->getMockBuilder('AsyncSockets\Socket\SocketInterface')
                            ->getMockForAbstractClass();
        $decider = new ConstantLimitationSolver(2);
        $context = new ExecutionContext();

        $decider->initialize($mock, $context);
        for ($i = 0; $i < self::TEST_LIMIT; $i++) {
            $decision = $decider->decide($mock, $socket, $context, self::TEST_LIMIT);
            $this->decider->invokeEvent(
                new Event($mock, $socket, null, EventType::INITIALIZE),
                $mock,
                $socket,
                $context
            );
            self::assertEquals(
                LimitationSolverInterface::DECISION_OK,
                $decision,
                'Invalid decision in normal case'
            );
            $this->decider->invokeEvent(
                new Event($mock, $socket, null, EventType::FINALIZE),
                $mock,
                $socket,
                $context
            );
        }

        $decider->finalize($mock, $context);
    }
}
