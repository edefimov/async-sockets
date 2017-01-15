<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\PartialFrame;

/**
 * Class ReadEventTest
 */
class ReadEventTest extends IoEventTest
{
    /**
     * Frame for event
     *
     * @var FrameInterface
     */
    protected $frame;

    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new ReadEvent($this->executor, $this->socket, $this->context, $this->frame);
    }

    /** {@inheritdoc} */
    protected function getEventType()
    {
        return EventType::READ;
    }

    /** {@inheritdoc} */
    public function testGetters()
    {
        $event = parent::testGetters();
        /** @var ReadEvent $event */
        self::assertSame($this->frame, $event->getFrame());
        self::assertFalse($event->isPartial());
        return $event;
    }

    /**
     * testIsPartial
     *
     * @param FrameInterface $frame Frame object
     * @param bool           $isPartial Is response actually partial
     *
     * @return void
     * @dataProvider socketResponseDataProvider
     */
    public function testIsPartial(FrameInterface $frame, $isPartial)
    {
        $event = new ReadEvent($this->executor, $this->socket, $this->context, $frame);
        self::assertSame($frame, $event->getFrame());
        self::assertSame($isPartial, $event->isPartial());
    }

    /**
     * testOobDataWrite
     *
     * @param bool $isOutOfBand Flag is data are out of band
     *
     * @return void
     * @dataProvider boolDataProvider
     */
    public function testOobDataWrite($isOutOfBand)
    {
        $event = new ReadEvent(
            $this->executor,
            $this->socket,
            $this->context,
            $this->getMockForAbstractClass('AsyncSockets\Frame\FrameInterface'),
            $isOutOfBand
        );
        $events = [
            0 => EventType::READ,
            1 => EventType::OOB
        ];

        $idx = (int) (bool) $isOutOfBand;
        self::assertArrayHasKey($idx, $events, 'Incorrect event type');
        self::assertSame($events[$idx], $event->getType(), 'Incorrect event type');
    }

    /**
     * socketResponseDataProvider
     *
     * @return array
     */
    public function socketResponseDataProvider()
    {
        return [
            [ new Frame('', ''), false ],
            [ new PartialFrame(new Frame('', '')), true ],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->frame = new Frame('Test data', '127.0.0.1:9098');
    }
}
