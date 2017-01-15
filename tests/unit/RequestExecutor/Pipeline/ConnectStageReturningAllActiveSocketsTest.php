<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\LimitationSolverInterface;
use AsyncSockets\RequestExecutor\Pipeline\ConnectStageReturningAllActiveSockets;

/**
 * Class ConnectStageReturningAllActiveSocketsTest
 */
class ConnectStageReturningAllActiveSocketsTest extends ConnectStageTest
{
    /** {@inheritdoc} */
    protected function createStage()
    {
        return new ConnectStageReturningAllActiveSockets($this->executor, $this->eventCaller, $this->solver);
    }

    /**
     * testThatAlreadyRunningObjectWillBeReturned
     *
     * @return void
     */
    public function testThatAlreadyRunningObjectWillBeReturned()
    {
        $this->solver->expects(self::any())->method('decide')->willReturn(LimitationSolverInterface::DECISION_OK);
        $first = $this->createRequestDescriptor();

        $first->expects(self::any())->method('isRunning')->willReturn(true);
        $first->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );

        $connected = $this->stage->processStage([ $first, ]);
        self::assertTrue(
            in_array($first, $connected, true),
            'Running operation must be returned as connected'
        );
    }
}
