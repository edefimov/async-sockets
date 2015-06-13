<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\ChunkSocketResponse;
use AsyncSockets\Socket\SocketResponse;
use AsyncSockets\Socket\SocketResponseInterface;

/**
 * Class ReadEventTest
 */
class ReadEventTest extends IoEventTest
{
    /**
     * Response for event
     *
     * @var SocketResponse
     */
    protected $response;

    /**
     * Mocked framePicker
     *
     * @var FramePickerInterface
     */
    protected $frame;



    /** {@inheritdoc} */
    protected function createEvent($type)
    {
        return new ReadEvent($this->executor, $this->socket, $this->context, $this->response);
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
        self::assertSame($this->response, $event->getResponse());
        self::assertFalse($event->isPartial());
        return $event;
    }

    /**
     * testIsPartial
     *
     * @param SocketResponseInterface $response Response object
     * @param bool                    $isPartial Is response actually partial
     *
     * @return void
     * @dataProvider socketResponseDataProvider
     */
    public function testIsPartial(SocketResponseInterface $response, $isPartial)
    {
        $event = new ReadEvent($this->executor, $this->socket, $this->context, $response);
        self::assertSame($response, $event->getResponse());
        self::assertSame($isPartial, $event->isPartial());
    }

    /**
     * socketResponseDataProvider
     *
     * @return array
     */
    public function socketResponseDataProvider()
    {
        static $mock;
        if (!$mock) {
            $mock = $this->getMock('AsyncSockets\Frame\FramePickerInterface');
        }

        return [
            [ new SocketResponse($mock, ''), false ],
            [ new ChunkSocketResponse($mock, ''), true ],
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->frame    = $this->getMock('AsyncSockets\Frame\FramePickerInterface');
        $this->response = new SocketResponse($this->frame, 'Test data');
    }
}
