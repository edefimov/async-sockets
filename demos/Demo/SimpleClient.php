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
use AsyncSockets\Socket\AsyncSocketFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SimpleClient
 */
final class SimpleClient extends Command
{
    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this->setName('demo:simple_client')
            ->setDescription('Demonstrates simple synchronous socket');
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $factory = new AsyncSocketFactory();

            $client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $client->open('tls://github.com:443');
            $client->write("GET / HTTP/1.1\nHost: github.com\n\n");
            $response = $client->read()->data();
            $client->close();

            $output->writeln((string) $response);
        } catch (SocketException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}
