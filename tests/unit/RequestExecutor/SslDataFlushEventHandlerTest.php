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

use AsyncSockets\Event\EventType;
use AsyncSockets\Frame\EmptyFrame;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\SslDataFlushEventHandler;

/**
 * Class SslDataFlushEventHandlerTest
 */
class SslDataFlushEventHandlerTest extends EventHandlerInterfaceTest
{
    /**
     * testCallNextHandler
     *
     * @return void
     */
    public function testCallNextHandler()
    {
        $next = $this->createMockedHandler();
        $ref  = new \ReflectionClass('AsyncSockets\Event\EventType');

        $events = $ref->getConstants();
        unset($events['DATA_ALERT'], $events['READ']);

        $eventCount = count($events);

        $object = new SslDataFlushEventHandler($next);

        $event = $this->getMockBuilder('AsyncSockets\Event\Event')
                      ->setMethods(['getType'])
                      ->disableOriginalConstructor()
                      ->getMockForAbstractClass();

        $next->expects(self::exactly($eventCount))
             ->method('invokeEvent')
             ->with($event);

        $event->expects(self::any())->method('getType')->willReturnOnConsecutiveCalls($events);
        for ($i = 0; $i < $eventCount; $i++) {
            $object->invokeEvent($event);
        }
    }

    /**
     * testCatchFirstDataAlert
     *
     * @return void
     */
    public function testCatchFirstDataAlert()
    {
        $next = $this->createMockedHandler();
        $next->expects(self::never())
             ->method('invokeEvent');

        $socket = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');
        $object = new SslDataFlushEventHandler($next);
        $event = $this->getMockBuilder('AsyncSockets\Event\DataAlertEvent')
                      ->setMethods(['getAttempt', 'nextIs', 'getType', 'getSocket'])
                      ->disableOriginalConstructor()
                      ->getMockForAbstractClass();

        $event->expects(self::any())
              ->method('getAttempt')
              ->willReturn(1);

        $event->expects(self::any())
              ->method('getSocket')
              ->willReturn($socket);

        $event->expects(self::any())
              ->method('getType')
              ->willReturn(EventType::DATA_ALERT);

        $event->expects(self::once())
            ->method('nextIs')
            ->willReturnCallback(function ($nextOperation) {
                self::assertInstanceOf(
                    'AsyncSockets\Operation\ReadOperation',
                    $nextOperation,
                    'Incorrect operation, only ReadOperation is applicable'
                );

                /** @var ReadOperation $nextOperation */
                $framePicker = $nextOperation->getFramePicker();
                self::assertInstanceOf(
                    'AsyncSockets\Frame\EmptyFramePicker',
                    $framePicker,
                    'Incorrect framepicker, only EmptyFramePicker is applicable'
                );
            });

        $object->invokeEvent($event);

        $event = $this->getMockBuilder('AsyncSockets\Event\ReadEvent')
                      ->setMethods(['getType', 'getSocket', 'getFrame'])
                      ->disableOriginalConstructor()
                      ->getMockForAbstractClass();
        $event->expects(self::any())
            ->method('getType')
            ->willReturn(EventType::READ);
        $event->expects(self::any())
            ->method('getSocket')
            ->willReturn($socket);
        $event->expects(self::any())
            ->method('getFrame')
            ->willReturn(new EmptyFrame(sha1(microtime())));

        $object->invokeEvent($event);
    }

    /**
     * testSkipAllOthersDataAlerts
     *
     * @return void
     */
    public function testSkipAllOthersDataAlerts()
    {
        $next = $this->createMockedHandler();

        $object = new SslDataFlushEventHandler($next);
        $event  = $this->getMockBuilder('AsyncSockets\Event\DataAlertEvent')
                      ->setMethods(['getAttempt', 'getType'])
                      ->disableOriginalConstructor()
                      ->getMockForAbstractClass();
        $event->expects(self::any())
            ->method('getAttempt')
            ->willReturn(2);

        $event->expects(self::any())
            ->method('getType')
            ->willReturn(EventType::DATA_ALERT);

        $next->expects(self::exactly(1))
             ->method('invokeEvent')
            ->with($event);

        $object->invokeEvent($event);
    }

    /**
     * testSkipEachFrame
     *
     * @param string $frameClass Frame class
     *
     * @return void
     * @dataProvider frameClassDataProvider
     */
    public function testSkipEachFrame($frameClass)
    {
        $frame = $this->getMockBuilder($frameClass)
                    ->disableOriginalConstructor()
                    ->getMockForAbstractClass();

        $socket = $this->getMockForAbstractClass('AsyncSockets\Socket\SocketInterface');

        $event = $this->getMockBuilder('AsyncSockets\Event\ReadEvent')
                      ->setMethods(['getType', 'getSocket', 'getFrame'])
                      ->disableOriginalConstructor()
                      ->getMockForAbstractClass();
        $event->expects(self::any())
              ->method('getType')
              ->willReturn(EventType::READ);
        $event->expects(self::any())
              ->method('getSocket')
              ->willReturn($socket);
        $event->expects(self::any())
              ->method('getFrame')
              ->willReturn($frame);


        $next = $this->createMockedHandler();
        $next->expects(self::exactly(1))
             ->method('invokeEvent')
             ->with($event);

        $object = new SslDataFlushEventHandler($next);
        $object->invokeEvent($event);
    }

    /**
     * frameClassDataProvider
     *
     * @return array
     */
    public function frameClassDataProvider()
    {
        return [
            ['AsyncSockets\Frame\AcceptedFrame'],
            ['AsyncSockets\Frame\EmptyFrame'],
            ['AsyncSockets\Frame\Frame'],
            ['AsyncSockets\Frame\PartialFrame'],
        ];
    }
}
