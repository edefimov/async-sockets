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

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RequestExecutorClient
 */
class RequestExecutorClient extends Command
{
    /**
     * OutputInterface
     *
     * @var OutputInterface
     */
    private $output;

    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this->setName('demo:request_executor_client')
            ->setDescription('Demonstrates usage of RequestExecutorInterface');
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $factory = new AsyncSocketFactory();

        $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
        $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

        $executor = $factory->createRequestExecutor();
        $this->registerPackagistSocket($executor, $client, 60, 0.001, 2);

        $executor->socketBag()->addSocket(
            $anotherClient,
            new WriteOperation("GET / HTTP/1.1\nHost: github.com\n\n"),
            [
                RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
            ],
            new CallbackEventHandler(
                [
                    EventType::DISCONNECTED => [ $this, 'onGitHubDisconnect' ],
                    EventType::CONNECTED    => [ $this, 'onGitHubConnected' ],
                ]
            )
        );

        $executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::CONNECTED => function () {
                        echo "Some socket connected\n";
                    },
                    EventType::DISCONNECTED => function () {
                        echo "Some socket disconnected\n";
                    },
                    EventType::INITIALIZE => [$this, 'logEvent'],
                    EventType::WRITE      => [ [$this, 'logEvent'], [$this, 'onWrite'] ],
                    EventType::READ       => [ [$this, 'logEvent'], [$this, 'onRead'] ],
                    EventType::EXCEPTION  => [$this, 'onException'],
                    EventType::TIMEOUT    => [$this, 'onTimeout'],
                ]
            )
        );

        $executor->executeRequest();
        $this->output = null;
    }

    /**
     * Log event
     *
     * @param Event $event Event object
     *
     * @return void
     */
    public function logEvent(Event $event)
    {
        $now  = new \DateTime();
        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
        $this->output->writeln('[' . $now->format('Y-m-d H:i:s') . '] ' . $event->getType() . ' on socket ' .
             $meta[RequestExecutorInterface::META_ADDRESS]);
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
        $event->nextIsRead(new MarkerFramePicker(null, '</html>', false));
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
        $context = $event->getContext();
        $socket  = $event->getSocket();
        $meta    = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());

        $context['response'] = $event->getFrame()->getData();

        $this->output->writeln("<info>{$meta[RequestExecutorInterface::META_ADDRESS]}  read " .
             number_format(strlen($context['response']), 0, ',', ' ') . ' bytes</info>');

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
     * @param Event $event Event object
     *
     * @return void
     */
    public function onPackagistDisconnect(Event $event)
    {
        $context  = $event->getContext();
        $socket   = $event->getSocket();
        $executor = $event->getExecutor();
        $meta     = $executor->socketBag()->getSocketMetaData($socket);

        $isTryingOneMoreTime = isset($context[ 'attempts' ]) &&
            $context[ 'attempts' ] - 1 > 0 &&
            $meta[ RequestExecutorInterface::META_REQUEST_COMPLETE ];
        $this->output->writeln('Packagist socket has disconnected') ;
        if ($isTryingOneMoreTime) {
            $this->output->writeln('Trying to get data one more time');

            $context['attempts'] -= 1;

            // automatically try one more time
            $executor->socketBag()->removeSocket($socket);
            $this->registerPackagistSocket($executor, $socket, 30, 30, 1);
        }
    }

    /**
     * Disconnect event
     *
     * @return void
     */
    public function onGitHubDisconnect()
    {
        $this->output->writeln('GitHub socket has disconnected');
    }

    /**
     * Disconnect event
     *
     * @param Event $event Event object
     *
     * @return void
     */
    public function onPackagistConnected(Event $event)
    {
        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
        $this->output->writeln("Connected to Packagist: {$meta[RequestExecutorInterface::META_ADDRESS]}");
    }

    /**
     * Disconnect event
     *
     * @param Event $event Event object
     *
     * @return void
     */
    public function onGitHubConnected(Event $event)
    {
        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
        $this->output->writeln("Connected to GitHub: {$meta[RequestExecutorInterface::META_ADDRESS]}");
    }

    /**
     * Exception event
     *
     * @param SocketExceptionEvent $event Event object
     *
     * @return void
     */
    public function onException(SocketExceptionEvent $event)
    {
        $this->output->writeln('<error>Exception occured: ' .
            $event->getException()->getMessage() . '</error>');
    }

    /**
     * Timeout event
     *
     * @param Event $event Event object
     *
     * @return void
     */
    public function onTimeout(Event $event)
    {
        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
        $this->output->writeln(
            "<comment>Timeout happened on some socket {$meta[RequestExecutorInterface::META_ADDRESS]}</comment>"
        );
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
                    EventType::DISCONNECTED => [$this, 'onPackagistDisconnect'],
                    EventType::CONNECTED    => [$this, 'onPackagistConnected'],
                ]
            )
        );
    }
}
