<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
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
        $streamSelect->setCallable(
            function (array &$read = null, array &$write = null) {
                $read  = [ ];
                $write = [ ];

                return 0;
            }
        );
    }

    /**
     * prepareForTestTimeoutOnIo
     *
     * @return void
     */
    protected function prepareForTestTimeoutOnIo()
    {
        $streamSelect = PhpFunctionMocker::getPhpFunctionMocker('stream_select');
        $streamSelect->setCallable(
            function (array &$read = null, array &$write = null) {
                $read  = [ ];
                $write = [ ];

                return 1;
            }
        );
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
            $streamSelect->setCallable(
                function (array &$read = null, array &$write = null) use ($eventType) {
                    $read  = [ ];
                    $write = [ ];

                    return 0;
                }
            );
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
            $streamSelect->setCallable(
                function (array &$read = null, array &$write = null) use ($eventType) {
                    $read  = [ ];
                    $write = [ ];

                    return 0;
                }
            );
        }
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
        PhpFunctionMocker::getPhpFunctionMocker('stream_select')->restoreNativeHandler();
    }
}
