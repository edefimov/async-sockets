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
use AsyncSockets\Frame\FixedLengthFramePicker;
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
     * testSendLargeAmountOfData
     *
     * @return void
     * @group networking
     */
    public function testSendLargeAmountOfData()
    {
        $client = new ClientSocket();

        $serviceUrl = 'tls://posttestserver.com:443';
        $client->open($serviceUrl);

        $numChunks     = 10;
        $alphabet      = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 1);
        $fullData      = '';
        $chunkSize     = 8192;
        $boundary      = sha1(microtime(true));
        $frameEnd      = "\r\n--{$boundary}--\r\n";
        $dirOnServer   = sha1('async-sockets-tests');
        $selector      = new AsyncSelector();
        $fileHeader    = "--{$boundary}\r\n" .
                         "Content-Disposition: form-data; name=\"testFile\"; filename=\"test.txt\"\r\n" .
                         "Content-Type: application/octet-stream\r\n" .
                         "Content-Transfer-Encoding: binary\r\n\r\n";

        $contentLength = strlen($fileHeader) + $numChunks * $chunkSize + strlen($frameEnd);

        $selector->addSocketOperation($client, OperationInterface::OPERATION_WRITE);
        $selector->select(30);
        $client->write(
            "POST /post.php?dir={$dirOnServer} HTTP/1.0\r\n" .
            "Host: posttestserver.com\r\n" .
            "Content-Type: multipart/form-data; boundary={$boundary}\r\n" .
            "Content-Length: {$contentLength}\r\n\r\n" .
            $fileHeader
        );

        for ($i = 0; $i < $numChunks; $i++) {
            $data = '';
            for ($j = 0; $j < $chunkSize; $j++) {
                $data .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }

            $selector->addSocketOperation($client, OperationInterface::OPERATION_WRITE);
            $selector->select(30);

            $count = $client->write($data);
            self::assertEquals($chunkSize, $count, 'Incorrect amount of data sent');
            $fullData .= $data;
        }

        $selector->addSocketOperation($client, OperationInterface::OPERATION_WRITE);
        $selector->select(30);
        $client->write($frameEnd);


        $selector->addSocketOperation($client, OperationInterface::OPERATION_READ);
        $selector->select(30);
        $headersString = (string) $client->read(new MarkerFramePicker(null, "\r\n\r\n"));
        $headers       = $this->getHeadersFromResponse($headersString);

        self::assertArrayHasKey('Content-Length', $headers, 'Unexpected response from server');

        $body = (string) $client->read(new FixedLengthFramePicker($headers['Content-Length']));

        list(,$url) = explode("\n", $body) + [1 => null];
        self::assertNotEmpty($url, 'Unexpected response returned from server');
        if (!preg_match('#(https?://.*)$#', trim($url), $pockets)) {
            self::fail('Target url not found at index 1');
        }

        $info = explode("\n", file_get_contents($pockets[1]));
        $info = array_filter($info);
        $url  = end($info);
        self::assertNotEmpty($url, 'Unexpected uploaded file info returned');
        if (!preg_match('#(https?://.*)$#', trim($url), $pockets)) {
            self::fail('File url not found');
        }

        $client->close();

        // read uploaded data
        $client = new ClientSocket();
        $client->open($serviceUrl);
        $selector->addSocketOperation($client, OperationInterface::OPERATION_WRITE);
        $selector->select(30);
        $path = parse_url($pockets[1], PHP_URL_PATH);
        $client->write(
            "GET {$path} HTTP/1.0\r\n" .
            "Host: posttestserver.com\r\n\r\n"
        );

        $selector->addSocketOperation($client, OperationInterface::OPERATION_READ);
        $selector->select(30);
        $headersString = $client->read(new MarkerFramePicker(null, "\r\n\r\n", false));
        $headers       = $this->getHeadersFromResponse($headersString);


        self::assertArrayHasKey('Content-Length', $headers, 'Unexpected response from server during read phase');

        $picker = new FixedLengthFramePicker($headers['Content-Length']);
        do {
            $selector->addSocketOperation($client, OperationInterface::OPERATION_READ);
            $selector->select(30);
            $response = $client->read($picker);
        } while ($response instanceof PartialFrame);

        self::assertEquals($fullData, (string) $response, 'Incorrect frame received');
    }

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
     * getHeadersFromResponse
     *
     * @param string $response Response
     *
     * @return array
     */
    private function getHeadersFromResponse($response)
    {
        $headers       = [];
        foreach (explode("\r\n", trim($response)) as $header) {
            list($name, $value) = explode(':', $header, 2) + [ 1 => null ];
            $headers[trim($name)] = trim($value);
        }

        return $headers;
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
