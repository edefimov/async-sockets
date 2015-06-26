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

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ReadOperation;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UdpEchoServer
 */
class UdpEchoServer extends Command
{
    /**
     * Handlers for client
     *
     * @var EventHandlerInterface
     */
    private $clientHandlers;

    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('demo:udp_server')
            ->setDescription('Demonstrates example usage of udp server sockets')
            ->setHelp('Demonstrates example usage of udp server sockets')
            ->setHelp('Starts HTTP server on passed host and port and allows to serve library files via browser')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host name to bind', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen', '10031')
        ;
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory       = new AsyncSocketFactory();
        $serverSocket  = $factory->createSocket(AsyncSocketFactory::SOCKET_SERVER);
        $executor      = $factory->createRequestExecutor();

        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');
        $output->writeln("<info>Starting UDP ECHO server on {$host}:{$port}</info>");
        $output->writeln('<comment>Press CTRL+C to exit</comment>');
        $executor->socketBag()->addSocket(
            $serverSocket,
            new ReadOperation(),
            [
                RequestExecutorInterface::META_ADDRESS            => "udp://{$host}:{$port}",
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => RequestExecutorInterface::WAIT_FOREVER,
                RequestExecutorInterface::META_IO_TIMEOUT         => RequestExecutorInterface::WAIT_FOREVER,
            ],
            new CallbackEventHandler(
                [
                    EventType::ACCEPT => function (AcceptEvent $event) use ($output) {
                        $output->writeln("<info>Incoming connection from {$event->getRemoteAddress()}</info>");
                        $event->getExecutor()->socketBag()->addSocket(
                            $event->getClientSocket(),
                            new ReadOperation(),
                            [
                                RequestExecutorInterface::META_USER_CONTEXT => $event->getRemoteAddress()
                            ],
                            $this->getAcceptedClientHandlers($output)
                        );
                    },
                    EventType::EXCEPTION => $this->getExceptionHandler($output)
                ]
            )
        );

        $executor->executeRequest();
    }

    /**
     * Return exception handler
     *
     * @param OutputInterface $output Output interface
     *
     * @return callable
     */
    private function getExceptionHandler(OutputInterface $output)
    {
        return function (SocketExceptionEvent $event) use ($output) {
            $type = $event->getOriginalEvent() ? $event->getOriginalEvent()->getType() : '';
            $output->writeln(
                '<error>Exception during processing ' . $type . ': ' .
                $event->getException()->getMessage() . '</error>'
            );
        };
    }

    /**
     * Return handler for clients
     *
     * @param OutputInterface $output Output interface
     *
     * @return EventHandlerInterface
     */
    private function getAcceptedClientHandlers(OutputInterface $output)
    {
        if (!$this->clientHandlers) {
            $this->clientHandlers = new CallbackEventHandler(
                [
                    EventType::READ => function (ReadEvent $event) use ($output) {
                        $request       = $event->getFrame()->data();
                        $remoteAddress = $event->getContext();
                        $output->writeln($remoteAddress . ' sent: ' . $request);
                        $event->nextIsWrite('Echo: ' . $request);
                    },
                    EventType::DISCONNECTED => function () use ($output) {
                        $output->writeln('Client disconnected');
                    },
                    EventType::EXCEPTION => $this->getExceptionHandler($output)
                ]
            );
        }

        return $this->clientHandlers;
    }
}
