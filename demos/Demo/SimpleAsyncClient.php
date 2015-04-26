<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo;

use AsyncSockets\Exception\SocketException;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class SimpleAsyncClient
 */
final class SimpleAsyncClient
{
    /**
     * Main
     *
     * @return void
     * @throws \Exception
     */
    public function main()
    {
        try {
            $factory = new AsyncSocketFactory();

            $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

            $selector = $factory->createSelector();
            $selector->addSocketOperationArray([
                [$client, RequestExecutorInterface::OPERATION_WRITE],
                [$anotherClient, RequestExecutorInterface::OPERATION_WRITE],
            ]);

            $data = [
                spl_object_hash($client) => [
                    'address' => 'tls://github.com:443',
                    'data'    => "GET / HTTP/1.1\nHost: github.com\n\n"
                ],


                spl_object_hash($anotherClient) => [
                    'address' => 'tls://packagist.org:443',
                    'data'    => "GET / HTTP/1.1\nHost: packagist.org\n\n"
                ],
            ];

            $numReadClient = 0;
            $aliveClients  = 2;

            foreach ([$client, $anotherClient] as $socket) {
                /** @var SocketInterface $socket */
                $item = $data[spl_object_hash($socket)];
                $socket->open($item['address']);
                $socket->setBlocking(false);
            }

            do {
                $context = $selector->select(5);
                foreach ($context->getWrite() as $socket) {
                    try {
                        $socket->write($data[spl_object_hash($socket)]['data']);
                        $selector->changeSocketOperation($socket, RequestExecutorInterface::OPERATION_READ);
                    } catch (SocketException $e) {
                        $selector->removeAllSocketOperations($socket);
                        $aliveClients -= 1;
                    }

                }

                foreach ($context->getRead() as $socket) {
                    $numReadClient += 1;
                    echo $socket->read() . "\n\n\n";
                    $selector->removeAllSocketOperations($socket);
                }
            } while ($numReadClient < $aliveClients);

        } catch (SocketException $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
