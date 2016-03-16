<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\SystemNotificationSample;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\EventHandlerFromSymfonyEventDispatcher;
use AsyncSockets\RequestExecutor\EventMultiHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Client
 */
class Client
{
    /**
     * EventDispatcherInterface
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Output
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * Constructor
     *
     * @param EventDispatcherInterface $eventDispatcher Event dispatcher
     * @param OutputInterface $output Output for application
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, OutputInterface $output)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->output          = $output;
    }

    /**
     * Process
     *
     * @return void
     */
    public function process()
    {
        $factory = new AsyncSocketFactory();

        $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
        $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

        $executor = $factory->createRequestExecutor();

        $this->registerPackagistSocket($executor, $client, 60, 60, 2);

        $executor->socketBag()->addSocket(
            $anotherClient,
            new WriteOperation("GET / HTTP/1.1\nHost: github.com\n\n"),
            [
                RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
            ]
        );


        $executor->withEventHandler(
            new EventMultiHandler(
                [
                    new CallbackEventHandler(
                        [
                            EventType::WRITE => [ $this, 'onWrite' ],
                            EventType::READ  => [ $this, 'onRead' ],
                        ]
                    ),
                    new EventHandlerFromSymfonyEventDispatcher($this->eventDispatcher)
                ]
            )
        );

        $executor->executeRequest();
    }

    /**
     * Write event
     *
     * @param WriteEvent $event Event object
     *
     * @return void
     */
    public function onWrite(WriteEvent $event)
    {
        $this->output->writeln(
            '<info>About to write</info>: ' .
            number_format(strlen($event->getOperation()->getData()), 0, '.', ' ') .
            ' bytes'
        );
        $event->nextIsRead(new MarkerFramePicker('HTTP', "\r\n\r\n"));
    }

    /**
     * Read event
     *
     * @param ReadEvent $event Event object
     *
     * @return void
     */
    public function onRead(ReadEvent $event)
    {
        $context       = $event->getContext();
        $socket        = $event->getSocket();
        $remoteAddress = $event->getFrame()->getRemoteAddress();

        $this->output->writeln(
            "<info>Received headers from {$remoteAddress}</info>: \n\n" . $event->getFrame()->getData()
        );

        $event->getExecutor()->socketBag()->setSocketMetaData(
            $socket,
            RequestExecutorInterface::META_USER_CONTEXT,
            $context
        );
        $event->nextOperationNotRequired();
    }

    /**
     * Disconnect event
     *
     * @return void
     */
    public function onPackagistDisconnect()
    {
        $this->output->writeln('Packagist socket has disconnected');
    }

    /**
     * Register packagist socket in request executor
     *
     * @param RequestExecutorInterface $executor          Executor
     * @param SocketInterface          $client            Client
     * @param int                      $connectionTimeout Connection timeout
     * @param double                   $ioTimeout         Read/Write timeout
     * @param int                      $attempts          Attempt count
     *
     * @return void
     */
    private function registerPackagistSocket(
        RequestExecutorInterface $executor,
        SocketInterface $client,
        $connectionTimeout,
        $ioTimeout,
        $attempts
    ) {
        $executor->socketBag()->addSocket(
            $client,
            new WriteOperation("GET / HTTP/1.1\nHost: packagist.org\n\n"),
            [
                RequestExecutorInterface::META_ADDRESS            => 'tls://packagist.org:443',
                RequestExecutorInterface::META_USER_CONTEXT       => [
                    'attempts' => $attempts,
                ],
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => $connectionTimeout,
                RequestExecutorInterface::META_IO_TIMEOUT         => $ioTimeout,
            ],
            new CallbackEventHandler(
                [
                    EventType::DISCONNECTED => [ $this, 'onPackagistDisconnect' ],
                ]
            )
        );
    }
}
