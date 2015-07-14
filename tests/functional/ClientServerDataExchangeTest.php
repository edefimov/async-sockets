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

use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\AsyncSocketFactory;
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
     * downloadInternetPage
     *
     * @param string $address Destination address
     *
     * @return void
     * @dataProvider internetPageDataProvider
     * @coversNothing
     * @group networking
     */
    public function testDownloadInternetPage($address)
    {
        $factory  = new AsyncSocketFactory();
        $selector = new AsyncSelector();

        try {
            $client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $client->open($address);
            $host = parse_url($address, PHP_URL_HOST);
            $selector->addSocketOperation($client, OperationInterface::OPERATION_WRITE);
            $selector->select(30);
            $client->write("GET / HTTP/1.1\nHost: {$host}\n\n");

            do {
                $selector->addSocketOperation($client, OperationInterface::OPERATION_READ);
                $selector->select(30);
                $response = $client->read(new MarkerFramePicker(null, '</html>', false));
            } while ($response instanceof PartialFrame);

            $client->close();

            self::assertTrue(stripos((string) $response, '</html>') !== false, 'Unexpected response');
        } catch (TimeoutException $e) {
            self::markTestSkipped('Timeout occurred during request processing');
        } catch (SocketException $e) {
            self::markTestSkipped('Can not process test: ' . $e->getMessage());
        }
    }

    /**
     * internetPageDataProvider
     *
     * @return array
     */
    public function internetPageDataProvider()
    {
        return [
            [ 'tcp://google.com:80' ],
            [ 'tcp://php.net:80' ],
            [ 'tls://github.com:443' ],
            [ 'tls://packagist.org:443' ],
            [ 'tls://coveralls.io:443' ],
            [ 'tcp://stackoverflow.com:80' ],
            [ 'tls://google.com:443' ],
        ];
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
