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

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\IoEvent;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\RequestExecutor\EventDispatcherAwareRequestExecutor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
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
     * Constructor
     *
     * @param EventDispatcherInterface $eventDispatcher Event dispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
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
        if ($executor instanceof EventDispatcherAwareRequestExecutor) {
            $executor->setEventDispatcher($this->eventDispatcher);
        }

        $this->registerPackagistSocket($executor, $client, 60, 0.001, 2);

        $executor->addSocket($anotherClient, RequestExecutorInterface::OPERATION_WRITE, [
            RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
            RequestExecutorInterface::META_USER_CONTEXT => [
                'data' => "GET / HTTP/1.1\nHost: github.com\n\n",
            ]
        ]);


        $executor->addHandler(
            [
                EventType::WRITE => [ $this, 'onWrite' ],
                EventType::READ  => [ $this, 'onRead' ],
            ]
        );

        $executor->executeRequest();
    }

    /**
     * Write event
     *
     * @param IoEvent $event Event object
     *
     * @return void
     */
    public function onWrite(IoEvent $event)
    {
        $context = $event->getContext();
        $socket  = $event->getSocket();

        $lenWritten = $socket->write($context['data']);
        echo 'Written: ' . number_format($lenWritten, 0, '.', ' ') . " bytes\n";
        $event->nextIsRead();
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

        echo 'Received: ' . number_format(strlen($event->getResponse()->getData()), 0, '.', ' ') . " bytes\n";

        $event->getExecutor()->setSocketMetaData($socket, RequestExecutorInterface::META_USER_CONTEXT, $context);
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
        echo "Packagist socket has disconnected\n";

        $context  = $event->getContext();
        $socket   = $event->getSocket();
        $executor = $event->getExecutor();
        $meta     = $executor->getSocketMetaData($socket);

        $isTryingOneMoreTime = isset($context[ 'attempts' ]) &&
                               $context[ 'attempts' ] - 1 > 0 &&
                               $meta[ RequestExecutorInterface::META_REQUEST_COMPLETE ];
        if ($isTryingOneMoreTime) {
            echo "Trying to get data one more time\n";

            $context['attempts'] -= 1;

            // automatically try one more time
            $executor->removeSocket($socket);
            $this->registerPackagistSocket($executor, $socket, 30, 30, 1);
        }
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
        $executor->addSocket(
            $client,
            RequestExecutorInterface::OPERATION_WRITE,
            [
                RequestExecutorInterface::META_ADDRESS => 'tls://packagist.org:443',
                RequestExecutorInterface::META_USER_CONTEXT => [
                    'data'     => "GET / HTTP/1.1\nHost: packagist.org\n\n",
                    'attempts' => $attempts
                ],

                RequestExecutorInterface::META_CONNECTION_TIMEOUT => $connectionTimeout,
                RequestExecutorInterface::META_IO_TIMEOUT         => $ioTimeout
            ]
        );

        $executor->addHandler(
            [
                EventType::DISCONNECTED => [ $this, 'onPackagistDisconnect' ],
            ],
            $client
        );
    }
}
