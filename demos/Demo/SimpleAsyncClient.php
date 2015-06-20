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
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\RequestExecutor\OperationInterface;
use AsyncSockets\Socket\AsyncSelector;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SimpleAsyncClient
 */
final class SimpleAsyncClient extends Command
{
    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this->setName('demo:simple_async_client')
            ->setDescription('Demonstrates select-like usage of library');
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $factory = new AsyncSocketFactory();

            $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

            $selector = new AsyncSelector();
            $selector->addSocketOperationArray(
                [
                    [ $client, OperationInterface::OPERATION_WRITE ],
                    [ $anotherClient, OperationInterface::OPERATION_WRITE ],
                ]
            );

            $data = [
                spl_object_hash($client) => [
                    'address'      => 'tls://github.com:443',
                    'data'         => "GET / HTTP/1.1\nHost: github.com\n\n",
                    'lastResponse' => '',
                ],


                spl_object_hash($anotherClient) => [
                    'address' => 'tls://packagist.org:443',
                    'data'    => "GET / HTTP/1.1\nHost: packagist.org\n\n",
                    'lastResponse' => '',
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
                        $selector->changeSocketOperation($socket, OperationInterface::OPERATION_READ);
                    } catch (SocketException $e) {
                        $selector->removeAllSocketOperations($socket);
                        $aliveClients -= 1;
                    }
                }

                foreach ($context->getRead() as $socket) {
                    $frame = $socket->read();
                    $hash     = spl_object_hash($socket);
                    if (!($frame instanceof PartialFrame)) {
                        $numReadClient += 1;
                        $output->writeln("<info>Response from {$data[$hash]['address']}</info>");
                        $output->writeln($frame->data() . "\n");
                        $selector->removeAllSocketOperations($socket);
                    } else {
                        $data[spl_object_hash($socket)]['lastResponse'] .= (string) $frame;
                        $selector->addSocketOperation($socket, OperationInterface::OPERATION_READ);
                    }
                }
            } while ($numReadClient < $aliveClients);

        } catch (SocketException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}
