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

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\Event\EventType;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\LibEventRequestExecutor;
use AsyncSockets\RequestExecutor\Pipeline\BaseStageFactory;
use Tests\Application\Mock\PhpFunctionMocker;
use Tests\AsyncSockets\RequestExecutor\LibEvent\LibEventEmulatedEvent;
use Tests\AsyncSockets\RequestExecutor\LibEvent\LibEventLoopEmulator;
use Tests\AsyncSockets\RequestExecutor\LibEvent\LibEventTestStageFactory;

/**
 * Class LibEventRequestExecutorTest
 */
class LibEventRequestExecutorTest extends AbstractRequestExecutorTest
{
    /**
     * LibEventLoopEmulator
     *
     * @var LibEventLoopEmulator
     */
    private $emulator;

    /** {@inheritdoc} */
    protected function createRequestExecutor()
    {
        if (!extension_loaded('libevent')) {
            self::markTestSkipped('To pass this test libevent extension must be installed');
        }
        return new LibEventRequestExecutor(new BaseStageFactory(), new Configuration());
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->emulator = new LibEventLoopEmulator();
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        if (extension_loaded('libevent')) {
            PhpFunctionMocker::getPhpFunctionMocker('event_set')->restoreNativeHandler();
            PhpFunctionMocker::getPhpFunctionMocker('event_add')->restoreNativeHandler();
            PhpFunctionMocker::getPhpFunctionMocker('event_new')->restoreNativeHandler();
        }
    }

    /**
     * prepareForTestTimeoutOnConnect
     *
     * @return void
     */
    protected function prepareForTestTimeoutOnConnect()
    {
        $this->emulator->onBeforeEvent(function (LibEventEmulatedEvent $event) {
            $event->setEventFlags(EV_TIMEOUT);
        });
    }

    /**
     * prepareForTestTimeoutOnIo
     *
     * @return void
     */
    protected function prepareForTestTimeoutOnIo()
    {
        $this->emulator->onBeforeEvent(function(LibEventEmulatedEvent $event) use (&$ioCount) {
            $event->setEventFlags(EV_TIMEOUT);
        });
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
            $this->emulator->onBeforeEvent(function(LibEventEmulatedEvent $event) use ($eventType) {
                $event->setEventFlags(EV_TIMEOUT);
            });
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
            $this->emulator->onBeforeEvent(function(LibEventEmulatedEvent $event) use ($eventType) {
                $event->setEventFlags(EV_TIMEOUT);
            });
        }
    }

    /**
     * testThatConnectionLessSocketWillFireEventImmediately
     *
     * @return void
     */
    public function testThatConnectionLessSocketWillFireEventImmediately()
    {
        $socket = $this->getMockBuilder('AsyncSockets\Socket\UdpClientSocket')
                    ->disableOriginalConstructor()
                    ->getMock();

        $libEventHandler = $this->getMockBuilder('Countable')
                                ->setMethods(['count'])
                                ->getMockForAbstractClass();

        $libEventHandler->expects(self::never())->method('count');
        PhpFunctionMocker::getPhpFunctionMocker('event_set')->setCallable([$libEventHandler, 'count']);
        PhpFunctionMocker::getPhpFunctionMocker('event_add')->setCallable([$libEventHandler, 'count']);
        PhpFunctionMocker::getPhpFunctionMocker('event_new')->setCallable([$libEventHandler, 'count']);

        $factory  = new LibEventTestStageFactory();
        $executor = new LibEventRequestExecutor($factory, new Configuration());

        $stub = $this->getMockBuilder('AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface')
                            ->setMethods(['processStage'])
                            ->getMockForAbstractClass();
        $stub->expects(self::any())->method('processStage')->willReturnArgument(0);

        $connectStage = $this->getMockBuilder('AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface')
                     ->setMethods(['processStage'])
                     ->getMockForAbstractClass();

        $connectStage->expects(self::at(0))->method('processStage')->willReturnArgument(0);
        $connectStage->expects(self::at(1))->method('processStage')->willReturn([]);
        $factory->setConnectStage($connectStage);
        $factory->setIoStage($stub);
        $factory->setDisconnectStage($stub);

        $executor->socketBag()->addSocket($socket, new ReadOperation());
        $executor->executeRequest();
    }
}
