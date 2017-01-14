<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\Functional;

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Socket\ClientSocket;
use AsyncSockets\Socket\ServerSocket;

/**
 * Class OobDataExchangeTest
 */
class OobDataExchangeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testSendOobDataWithStream
     *
     * @param string $address Server address
     * @param string $data Data to send
     *
     * @return void
     * @throws \Exception
     * @dataProvider localAddressDataProvider
     */
    public function testSendOobDataWithStream($address, $data)
    {
        $server = new ServerSocket();
        $client = new ClientSocket();
        try {
            $server->open($address);

            $openedAddress = substr($address, 0, strpos($address, '://')) . '://' .
                             stream_socket_get_name($server->getStreamResource(), false);

            $client->open($openedAddress);

            $written = $client->write($data, true);
            self::assertSame(strlen($data), $written, 'Incorrect length of written data');

            /** @var AcceptedFrame $frame */
            $frame          = $server->read(new RawFramePicker());
            $acceptedClient = $frame->getClientSocket();
            $acceptedClient->open($openedAddress);
            $acceptedData = (string) $acceptedClient->read(new RawFramePicker(), true);

            self::assertSame($data, $acceptedData, 'Incorrect data received by OOB channel.');

            $server->close();
            $client->close();
        } catch (\Exception $e) {
            $server->close();
            $client->close();
            throw $e;
        }
    }

    /**
     * localAddressDataProvider
     *
     * @return array
     */
    public function localAddressDataProvider()
    {
        $random = sha1(microtime(true));
        return [
            ['tcp://127.0.0.1:0', $random[0] ],
        ];
    }
}
