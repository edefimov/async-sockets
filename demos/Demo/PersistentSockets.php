<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo;

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\Operation\DelayedOperation;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\RemoveFinishedSocketsEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
use Demo\Component\ReadRemoteDataSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PersistentSockets
 */
class PersistentSockets extends Command
{
    /**
     * Handlers for client
     *
     * @var EventHandlerInterface
     */
    private $clientHandlers;

    /**
     * Socket to remote host
     *
     * @var SocketInterface
     */
    private $remoteSocket;

    /**
     * Remote data synchronizer
     *
     * @var ReadRemoteDataSynchronizer
     */
    private $remoteSynchronizer;

    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('demo:persistent_socket')
            ->setDescription('Demonstrates example usage of persistent sockets server sockets ')
            ->setHelp(
                'Starts HTTP server on passed host and port and makes connection to given host via persistent socket'
            )
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host name to bind', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen', '10033')
            ->addOption(
                'destination',
                null,
                InputOption::VALUE_OPTIONAL,
                'Host to use for persistence connections',
                'tcp://packagist.org:443'
            );
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory      = new AsyncSocketFactory();
        $serverSocket = $factory->createSocket(AsyncSocketFactory::SOCKET_SERVER);
        $executor     = $factory->createRequestExecutor();

        $host        = $input->getOption('host');
        $port        = (int) $input->getOption('port');
        $destination = $input->getOption('destination');

        $this->remoteSynchronizer = new ReadRemoteDataSynchronizer($destination, $output);
        $this->remoteSocket       = $factory->createSocket(
            AsyncSocketFactory::SOCKET_CLIENT,
            [
                AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT  => true,
                AsyncSocketFactory::SOCKET_OPTION_PERSISTENT_KEY => 'remote',
            ]
        );

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $message   = $formatter->formatBlock(
            [ "Starting HTTP server on {$host}:{$port}", 'Press CTRL+C to exit' ],
            'info'
        );
        $output->writeln($message);
        $executor->socketBag()->addSocket(
            $serverSocket,
            new ReadOperation(),
            [
                RequestExecutorInterface::META_ADDRESS            => "tcp://{$host}:{$port}",
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => RequestExecutorInterface::WAIT_FOREVER,
                RequestExecutorInterface::META_IO_TIMEOUT         => RequestExecutorInterface::WAIT_FOREVER,
            ],
            new CallbackEventHandler(
                [
                    EventType::ACCEPT    => function (AcceptEvent $event) use ($output) {
                        $output->writeln("<info>Incoming connection from {$event->getRemoteAddress()}</info>");
                        $event->getExecutor()->socketBag()->addSocket(
                            $event->getClientSocket(),
                            new ReadOperation(new MarkerFramePicker('HTTP', "\r\n\r\n")),
                            [ ],
                            $this->getAcceptedClientHandlers($output)
                        );
                    },
                    EventType::EXCEPTION => $this->getExceptionHandler($output),
                ]
            )
        );

        $executor->executeRequest();
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
            $this->clientHandlers = new RemoveFinishedSocketsEventHandler(
                new CallbackEventHandler(
                    [
                        EventType::READ         => function (ReadEvent $event) {
                            $socketBag = $event->getExecutor()->socketBag();

                            /** Do *NOT* write such a fragment in real applications, it is just for simplicity! */
                            if (!$socketBag->hasSocket($this->remoteSocket)) {
                                $socketBag->addSocket(
                                    $this->remoteSocket,
                                    $this->remoteSynchronizer->getWriteOperation(),
                                    [
                                        RequestExecutorInterface::META_IO_TIMEOUT
                                            => RequestExecutorInterface::WAIT_FOREVER,
                                    ],
                                    $this->remoteSynchronizer
                                );
                            } else {
                                $this->remoteSynchronizer->reset();
                                $socketBag->setSocketOperation(
                                    $this->remoteSocket,
                                    $this->remoteSynchronizer->getWriteOperation()
                                );
                            }

                            $event->nextIs(
                                new DelayedOperation(
                                    new WriteOperation(),
                                    function (
                                        SocketInterface $socket,
                                        RequestExecutorInterface $executor
                                    ) {
                                        if ($this->remoteSynchronizer->isResolved()) {
                                            $response  = $this->remoteSynchronizer->getData();
                                            $operation = $executor->socketBag()->getSocketOperation($socket);
                                            /** @var DelayedOperation $operation */
                                            $operation = $operation->getOriginalOperation();
                                            /** @var WriteOperation $operation */
                                            $operation->setData(
                                                "HTTP/1.1 200 OK \r\n" .
                                                "Content-Type: text/html;charset=utf-8\r\n" .
                                                'Content-Length: ' . strlen($response) . "\r\n\r\n" .
                                                $response
                                            );

                                            $executor->socketBag()->setSocketOperation($socket, $operation);

                                            return false;
                                        }

                                        return true;
                                    }
                                )
                            );
                        },
                        EventType::DISCONNECTED => function () use ($output) {
                            $output->writeln('Client disconnected');
                        },
                        EventType::EXCEPTION    => $this->getExceptionHandler($output),
                    ]
                )
            );
        }

        return $this->clientHandlers;
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
            $output->writeln(
                '<error>Exception: ' .
                $event->getException()->getMessage() . '</error>'
            );
        };
    }
}
