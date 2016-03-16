<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket\Io;

use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\Io\DatagramMemorizedIo;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class DatagramMemorizedIoTest
 */
class DatagramMemorizedIoTest extends DatagramClientIoTest
{
    /**
     * Memorized data
     *
     * @var string
     */
    private $data;

    /** {@inheritdoc} */
    protected function createIoInterface(SocketInterface $socket)
    {
        return new DatagramMemorizedIo($socket, '127.0.0.1:4325', $this->data);
    }

    /**
     * testReadData
     *
     * @return void
     */
    public function testReadData()
    {
        $this->ensureSocketIsOpened();
        $frame = $this->object->read(new RawFramePicker());
        self::assertEquals($this->data, (string) $frame, 'Incorrect frame');

        $frame = $this->object->read(new RawFramePicker());
        self::assertEmpty((string) $frame, 'Second read must not return anything');
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->data = md5(microtime(true));
        parent::setUp();
    }
}
