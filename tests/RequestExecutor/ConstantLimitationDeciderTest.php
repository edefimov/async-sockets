<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\RequestExecutor\ConstantLimitationDecider;
use AsyncSockets\RequestExecutor\LimitationDeciderInterface;
use AsyncSockets\RequestExecutor\RequestExecutor;

/**
 * Class ConstantLimitationDeciderTest
 */
class ConstantLimitationDeciderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Limitation for test
     */
    const TEST_LIMIT = 10;

    /**
     * Decider object
     *
     * @var ConstantLimitationDecider
     */
    private $decider;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->decider = new ConstantLimitationDecider(self::TEST_LIMIT);
    }

    /**
     * testMakingDecision
     *
     * @return void
     */
    public function testExcessCount()
    {
        $mock   = new RequestExecutor();
        $socket = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $this->decider->initialize($mock);

        for ($i = 0; $i <= self::TEST_LIMIT; $i++) {
            $decision = $this->decider->decide($mock, $socket, self::TEST_LIMIT + 1);
            $this->decider->invokeEvent(
                new Event($mock, $socket, null, EventType::INITIALIZE)
            );
            if ($i < self::TEST_LIMIT) {
                self::assertEquals(
                    LimitationDeciderInterface::DECISION_OK,
                    $decision,
                    'Invalid decision in normal case'
                );
            } else {
                self::assertEquals(
                    LimitationDeciderInterface::DECISION_PROCESS_SCHEDULED,
                    $decision,
                    'Invalid excessed decision'
                );
            }
        }
        $this->decider->finalize($mock);
    }

    /**
     * testWithRequestComplete
     *
     * @return void
     */
    public function testWithRequestComplete()
    {
        $mock    = new RequestExecutor();
        $socket  = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $decider = new ConstantLimitationDecider(2);

        $decider->initialize($mock);
        for ($i = 0; $i < self::TEST_LIMIT; $i++) {
            $decision = $decider->decide($mock, $socket, self::TEST_LIMIT);
            $this->decider->invokeEvent(
                new Event($mock, $socket, null, EventType::INITIALIZE)
            );
            self::assertEquals(
                LimitationDeciderInterface::DECISION_OK,
                $decision,
                'Invalid decision in normal case'
            );
            $this->decider->invokeEvent(
                new Event($mock, $socket, null, EventType::FINALIZE)
            );
        }

        $decider->finalize($mock);
    }
}
