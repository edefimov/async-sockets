<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\Functional;

use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Socket\ClientSocket;
use AsyncSockets\Socket\ServerSocket;

/**
 * Class ClientServerDataExchangeTest
 */
class ClientServerDataExchangeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testClientServerDataExchange
     *
     * @param string $serverAddress Server address
     *
     * @return void
     * @throws \Exception
     * @dataProvider localAddressDataProvider
     * @covers \AsyncSockets\Socket\ClientSocket::createIoInterface
     * @covers \AsyncSockets\Socket\ServerSocket::createIoInterface
     */
    public function testClientServerDataExchange($serverAddress)
    {
        $cleanup = function () use ($serverAddress) {
            if (strpos($serverAddress, 'unix://') === 0) {
                unlink(substr($serverAddress, 7));
            }
        };

        try {
            $server = new ServerSocket();
            $client = new ClientSocket();

            $server->open($serverAddress);
            $openedAddress = substr($serverAddress, 0, strpos($serverAddress, '://')) . '://' .
                             stream_socket_get_name($server->getStreamResource(), false);
            $client->open($openedAddress);

            $data = md5(microtime(true));
            $client->write($data);

            $acceptedFrame = $server->read();
            self::assertInstanceOf('AsyncSockets\Frame\AcceptedFrame', $acceptedFrame);

            /** @var AcceptedFrame $acceptedFrame */
            $acceptedSocket = $acceptedFrame->getClientSocket();

            $acceptedSocket->open(null);
            $frame = $acceptedSocket->read();
            self::assertEquals($data, (string) $frame, 'Incorrect frame from client received');

            $anotherData = md5(microtime(true) * mt_rand(2, 4));
            $acceptedSocket->write($anotherData);
            $frame = $client->read();
            self::assertEquals($anotherData, (string) $frame, 'Incorrect frame from server received');

            $client->close();
            $server->close();
            $acceptedSocket->close();
        } catch (\Exception $e) {
            $cleanup();
            throw $e;
        }

        $cleanup();
    }

    /**
     * localAddressDataProvider
     *
     * @return array
     */
    public function localAddressDataProvider()
    {
        $tmpDir = sys_get_temp_dir();
        return [
            ['tcp://127.0.0.1:0'],
            ['udp://127.0.0.1:0'],
            ["unix:///{$tmpDir}/" . sha1(microtime(true))],
        ];
    }
}
