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

use AsyncSockets\Event\EventType;
use AsyncSockets\RequestExecutor\ConstantLimitationDecider;
use AsyncSockets\RequestExecutor\LimitationDecider;

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
        $mock   = $this->getMock('AsyncSockets\RequestExecutor\RequestExecutor');
        $socket = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $this->decider->initialize($mock);

        for ($i = 0; $i <= self::TEST_LIMIT; $i++) {
            $decision = $this->decider->decide($mock, $socket, self::TEST_LIMIT + 1);
            $this->decider->onSocketRequestInitialize();
            if ($i < self::TEST_LIMIT) {
                self::assertEquals(
                    LimitationDecider::DECISION_OK,
                    $decision,
                    'Invalid decision in normal case'
                );
            } else {
                self::assertEquals(
                    LimitationDecider::DECISION_PROCESS_SCHEDULED,
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
        $mock    = $this->getMock('AsyncSockets\RequestExecutor\RequestExecutor');
        $socket  = $this->getMock('AsyncSockets\Socket\SocketInterface');
        $decider = new ConstantLimitationDecider(2);

        $decider->initialize($mock);
        for ($i = 0; $i < self::TEST_LIMIT; $i++) {
            $decision = $decider->decide($mock, $socket, self::TEST_LIMIT);
            $this->decider->onSocketRequestInitialize();
            self::assertEquals(
                LimitationDecider::DECISION_OK,
                $decision,
                'Invalid decision in normal case'
            );
            $this->decider->onSocketRequestFinalize();
        }

        $decider->finalize($mock);
    }

    /**
     * testEventSubscribersAreSet
     *
     * @return void
     */
    public function testEventSubscribersAreSet()
    {
        $mock = $this->getMock(
            'AsyncSockets\RequestExecutor\RequestExecutor',
            ['addHandler', 'removeHandler']
        );
        $mock->expects(self::once())
            ->method('addHandler')
            ->with(
                [
                    EventType::INITIALIZE => [$this->decider, 'onSocketRequestInitialize'],
                    EventType::FINALIZE   => [$this->decider, 'onSocketRequestFinalize'],
                ]
            );

        $mock->expects(self::once())
            ->method('removeHandler')
            ->with(
                [
                    EventType::INITIALIZE => [$this->decider, 'onSocketRequestInitialize'],
                    EventType::FINALIZE   => [$this->decider, 'onSocketRequestFinalize'],
                ]
            );

        $this->decider->initialize($mock);
        $this->decider->finalize($mock);
    }
}
