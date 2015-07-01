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

            $socketContext = [
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

            $this->openConnection([$client, $anotherClient], $socketContext);

            do {
                $selectContext = $selector->select(5);
                $aliveClients -= $this->writeDataToSockets(
                    $selectContext->getWrite(),
                    $socketContext,
                    $selector
                );

                $numReadClient += $this->readDataFromSockets(
                    $output,
                    $selectContext->getRead(),
                    $socketContext,
                    $selector
                );
            } while ($numReadClient < $aliveClients);

        } catch (SocketException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }

    /**
     * Open connection
     *
     * @param SocketInterface[] $sockets List of sockets
     * @param array             $data Socket context
     *
     * @return void
     */
    private function openConnection(array $sockets, array $data)
    {
        foreach ($sockets as $socket) {
            /** @var SocketInterface $socket */
            $item = $data[ spl_object_hash($socket) ];
            $socket->open($item[ 'address' ]);
            $socket->setBlocking(false);
        }
    }

    /**
     * writeDataToSockets
     *
     * @param SocketInterface[] $sockets List of sockets to write data
     * @param array             $data Socket data context
     * @param AsyncSelector     $selector Selector object
     *
     * @return int Number of clients with errors
     */
    protected function writeDataToSockets(array $sockets, array $data, AsyncSelector $selector)
    {
        $result = 0;
        foreach ($sockets as $socket) {
            try {
                $socket->write($data[ spl_object_hash($socket) ][ 'data' ]);
                $selector->changeSocketOperation($socket, OperationInterface::OPERATION_READ);
            } catch (SocketException $e) {
                $selector->removeAllSocketOperations($socket);
                $result += 1;
            }
        }

        return $result;
    }

    /**
     * Read data from sockets
     *
     * @param OutputInterface   $output Output interface
     * @param SocketInterface[] $sockets List of sockets ready to read
     * @param array             &$data Socket context
     * @param AsyncSelector     $selector Selector
     *
     * @return int Amount of processed clients
     */
    protected function readDataFromSockets(
        OutputInterface $output,
        array $sockets,
        array &$data,
        AsyncSelector $selector
    ) {
        $result = 0;
        foreach ($sockets as $socket) {
            $frame = $socket->read();
            $hash  = spl_object_hash($socket);
            if (!($frame instanceof PartialFrame)) {
                $result += 1;
                $output->writeln("<info>Response from {$data[$hash]['address']}</info>");
                $output->writeln($frame->getData() . "\n");
                $selector->removeAllSocketOperations($socket);
            } else {
                $data[ spl_object_hash($socket) ][ 'lastResponse' ] .= (string) $frame;
                $selector->addSocketOperation($socket, OperationInterface::OPERATION_READ);
            }
        }

        return $result;
    }
}
