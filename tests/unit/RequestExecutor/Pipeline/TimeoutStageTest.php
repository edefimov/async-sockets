<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\TimeoutEvent;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\Pipeline\TimeoutStage;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class TimeoutStageTest
 */
class TimeoutStageTest extends AbstractStageTest
{
    /** {@inheritdoc} */
    protected function createStage()
    {
        return new TimeoutStage($this->executor, $this->eventCaller);
    }

    /**
     * testThatEventSubscribersWillBeCalled
     *
     * @return void
     */
    public function testThatEventSubscribersWillBeCalled()
    {
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable(
            function () {
                return 10;
            }
        );

        $request = $this->createRequestDescriptor();
        $request->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME] = 5;
        $metadata[RequestExecutorInterface::META_CONNECTION_TIMEOUT]    = 1;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::once())
            ->method('callSocketSubscribers')
            ->with($request)
            ->willReturnCallback(function (RequestDescriptor $request, Event $event) use ($metadata) {
                self::assertInstanceOf('AsyncSockets\Event\TimeoutEvent', $event);
                self::assertSame($request->getSocket(), $event->getSocket());
                self::assertSame(EventType::TIMEOUT, $event->getType());
                self::assertSame(
                    $metadata[ RequestExecutorInterface::META_USER_CONTEXT ],
                    $event->getContext(),
                    'Incorrect context'
                );
            });

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Timeout socket was not returned');
    }

    /**
     * testThatExceptionSubscribersWillBeCalled
     *
     * @return void
     */
    public function testThatExceptionSubscribersWillBeCalled()
    {
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable(
            function () {
                return 10;
            }
        );

        $request = $this->createRequestDescriptor();
        $request->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );

        $metadata = $this->getMetadataStructure();
        $metadata[RequestExecutorInterface::META_CONNECTION_START_TIME] = 5;
        $metadata[RequestExecutorInterface::META_CONNECTION_TIMEOUT]    = 1;
        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::at(0))
                          ->method('callSocketSubscribers')
                          ->with($request)
                          ->willThrowException(new NetworkSocketException($request->getSocket()));
        $this->eventCaller->expects(self::once())
            ->method('callExceptionSubscribers')
            ->with($request);

        $result = $this->stage->processStage([$request]);
        self::assertNotEmpty($result, 'Timeout socket was not returned');
    }

    /**
     * testTimeoutProcessing
     *
     * @param array $sockets Sockets data in form { metadata: { key -> value }, isTimeout: bool}
     * @param double $microtime Microtime value for php function
     *
     * @return void
     * @dataProvider timeoutDataProvider
     */
    public function testTimeoutProcessing(array $sockets, $microtime)
    {
        $requests        = [ ];
        $timeoutRequests = [ ];
        $normalRequests  = [ ];
        foreach ($sockets as $socket) {
            $request = $this->createRequestDescriptor();
            $request->expects(self::any())->method('getSocket')->willReturn(
                $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
            );
            $request->expects(self::any())->method('getOperation')->willReturn(new ReadOperation());

            $metadata = $this->getMetadataStructure();
            foreach ($socket['metadata'] as $metaConstName => $value) {
                $key = constant('AsyncSockets\RequestExecutor\RequestExecutorInterface::' . $metaConstName);

                $metadata[$key] = $value;
            }
            $request->expects(self::any())->method('getMetadata')->willReturn($metadata);
            $requests[] = $request;

            if ($socket['isTimeout']) {
                $timeoutRequests[] = $request;
            } else {
                $normalRequests[] = $request;
            }
        }

        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable(
            function () use ($microtime) {
                return $microtime;
            }
        );

        $result = $this->stage->processStage($requests);
        foreach ($timeoutRequests as $request) {
            $index = array_search($request, $requests, true);
            self::assertTrue(in_array($request, $result, true), "Timeout socket at index {$index} was not returned");
        }

        foreach ($normalRequests as $request) {
            $index = array_search($request, $requests, true);
            self::assertFalse(
                in_array($request, $result, true),
                "Normal socket at index {$index} was returned as timeout"
            );
        }
    }

    /**
     * testOneMoreAttempt
     *
     * @param int    $microtime Return value for microtime()
     * @param string $when Moment when timeout occured
     * @param array  $setMetadata Metadata to set before test in descriptor
     * @param array  $expectations Expectations for descriptor
     *
     * @dataProvider oneMoreAttemptDataProvider
     */
    public function testOneMoreAttempt($microtime, $when, array $setMetadata, array $expectations)
    {
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->setCallable(
            function () use ($microtime) {
                return $microtime;
            }
        );

        $request = $this->createRequestDescriptor();
        $request->expects(self::any())->method('getSocket')->willReturn(
            $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface')
        );

        foreach ($expectations as $method => $args) {
            $matcher = $request->expects(self::once())
                    ->method($method);

            call_user_func_array([$matcher, 'with'], $args);
        }


        $metadata = array_replace(
            $this->getMetadataStructure(),
            $setMetadata
        );

        $request->expects(self::any())->method('getMetadata')->willReturn($metadata);

        $this->eventCaller->expects(self::once())
                          ->method('callSocketSubscribers')
                          ->with($request)
                          ->willReturnCallback(function (RequestDescriptor $request, TimeoutEvent $event) use ($when) {
                              self::assertSame($when, $event->when(), 'Incorrect timeout moment');
                              $event->enableOneMoreAttempt();
                          });

        $result = $this->stage->processStage([$request]);
        self::assertEmpty($result, 'Socket with next attempt shouldn\'t return');
    }

    /**
     * oneMoreAttemptDataProvider
     *
     * @return array
     */
    public function oneMoreAttemptDataProvider()
    {
        return [
            [
                10,
                TimeoutEvent::DURING_IO,
                [
                    RequestExecutorInterface::META_CONNECTION_START_TIME  => 1,
                    RequestExecutorInterface::META_CONNECTION_FINISH_TIME => 10,
                    RequestExecutorInterface::META_LAST_IO_START_TIME     => 5,
                    RequestExecutorInterface::META_IO_TIMEOUT             => 1,
                ],
                [
                    'setMetadata' => [ RequestExecutorInterface::META_LAST_IO_START_TIME, null ]
                ]
            ],

            [
                10,
                TimeoutEvent::DURING_CONNECTION,
                [
                    RequestExecutorInterface::META_CONNECTION_START_TIME  => 1,
                    RequestExecutorInterface::META_CONNECTION_FINISH_TIME => null,
                    RequestExecutorInterface::META_CONNECTION_TIMEOUT     => 5,
                ],
                [
                    'setRunning' =>  [false],
                    'setMetadata' => [
                        [
                            RequestExecutorInterface::META_LAST_IO_START_TIME     => null,
                            RequestExecutorInterface::META_CONNECTION_START_TIME  => null,
                            RequestExecutorInterface::META_CONNECTION_FINISH_TIME => null,
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * timeoutDataProvider
     *
     * @param string $targetMethod Target method name
     *
     * @return array
     */
    public function timeoutDataProvider($targetMethod)
    {
        return $this->dataProviderFromYaml(__DIR__, __CLASS__, __FUNCTION__, $targetMethod);
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('microtime')->restoreNativeHandler();
    }
}
