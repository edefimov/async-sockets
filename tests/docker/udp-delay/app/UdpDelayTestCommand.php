<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\TimeoutEvent;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Operation\DelayedOperation;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\RemoveFinishedSocketsEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UdpDelayTestCommand
 */
class UdpDelayTestCommand extends Command
{
    /**
     * Handlers for client
     *
     * @var EventHandlerInterface
     */
    private $clientHandlers;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('test:udp-delay')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host name to bind', '0.0.0.0')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen', '10031');
    }

    /**
     * @inheritDoc
     */
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
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => 1,
                RequestExecutorInterface::META_IO_TIMEOUT         => 1,
            ],
            new CallbackEventHandler(
                [
                    EventType::ACCEPT => function (AcceptEvent $event) use ($output) {
                        $output->writeln("<info>Incoming connection from {$event->getRemoteAddress()}</info>");
                        $event->getExecutor()->socketBag()->addSocket(
                            $event->getClientSocket(),
                            new ReadOperation(new RawFramePicker()),
                            [
                                RequestExecutorInterface::META_USER_CONTEXT => $event->getRemoteAddress()
                            ],
                            $this->getAcceptedClientHandlers($output)
                        );
                    },
                    EventType::TIMEOUT => function (TimeoutEvent $event) {
                        $event->enableOneMoreAttempt();
                    },

                    EventType::EXCEPTION => $this->getExceptionHandler($output)
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
                        EventType::READ => function (ReadEvent $event) use ($output) {
                            $request       = $event->getFrame()->getData();
                            $remoteAddress = $event->getContext();
                            $startedWaitAt = time();
                            $output->writeln($remoteAddress . ' sent: ' . $request . ' waiting 5s before reply');
                            $event->nextIs(
                                new DelayedOperation(
                                    new WriteOperation($request),
                                    function () use ($startedWaitAt) {
                                        return time() - $startedWaitAt < 5;
                                    }
                                )
                            );
                        },
                        EventType::EXCEPTION => $this->getExceptionHandler($output)
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
