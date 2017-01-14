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

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\Event\EventType;
use AsyncSockets\RequestExecutor\NativeRequestExecutor;
use AsyncSockets\RequestExecutor\Pipeline\BaseStageFactory;
use AsyncSockets\RequestExecutor\Pipeline\PipelineFactory;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class NativeRequestExecutorTest
 */
class NativeRequestExecutorTest extends AbstractRequestExecutorTest
{
    /** {@inheritdoc} */
    protected function createRequestExecutor()
    {
        return new NativeRequestExecutor(
            new PipelineFactory(
                new BaseStageFactory()
            ),
            new Configuration()
        );
    }

    /**
     * prepareForTestTimeoutOnConnect
     *
     * @return void
     */
    protected function prepareForTestTimeoutOnConnect()
    {
        $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $streamSelect->setCallable([$this, 'streamSelectTimeout']);
    }

    /**
     * prepareForTestTimeoutOnIo
     *
     * @return void
     */
    protected function prepareForTestTimeoutOnIo()
    {
        $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $streamSelect->setCallable([$this, 'streamSelectTimeout']);
    }

    /**
     * prepareForTestThrowsNonSocketExceptionInEvent
     *
     * @param string $eventType Event type to throw exception in
     *
     * @return void
     */
    protected function prepareForTestThrowsNonSocketExceptionInEvent($eventType)
    {
        if ($eventType === EventType::TIMEOUT) {
            $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
            $streamSelect->setCallable([$this, 'streamSelectTimeout']);
        }
    }

    /**
     * prepareForTestThrowingSocketExceptionsInEvent
     *
     * @param string $eventType Event type to throw exception in
     *
     * @return void
     */
    protected function prepareForTestThrowingSocketExceptionsInEvent($eventType)
    {
        if ($eventType === EventType::TIMEOUT) {
            $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
            $streamSelect->setCallable([$this, 'streamSelectTimeout']);
        }
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
    }

    /**
     * Timeouted stream_select handler
     *
     * @param array &$read Read descriptors
     * @param array &$write Write descriptors
     * @param array &$oob OOB descriptors
     *
     * @return \Closure
     */
    public function streamSelectTimeout(array &$read = null, array &$write = null, array &$oob = null)
    {
        $read  = [ ];
        $write = [ ];
        $oob   = [ ];

        return 0;
    }
}
