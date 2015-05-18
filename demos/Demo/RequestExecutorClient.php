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
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class RequestExecutorClient
 */
class RequestExecutorClient
{
    /**
     * Main
     *
     * @return void
     */
    public function main()
    {
        $factory = new AsyncSocketFactory();

        $client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
        $anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

        $executor = $factory->createRequestExecutor();
        $this->registerPackagistSocket($executor, $client, 60, 0.001, 2);

        $executor->addSocket($anotherClient, RequestExecutorInterface::OPERATION_WRITE, [
            RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
            RequestExecutorInterface::META_USER_CONTEXT => [
                'data' => "GET / HTTP/1.1\nHost: github.com\n\n",
            ]
        ]);

        $executor->addHandler([
            EventType::DISCONNECTED => [$this, 'onGitHubDisconnect'],
            EventType::CONNECTED    => [$this, 'onGitHubConnected'],
        ], $anotherClient);

        $executor->addHandler([
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
        ]);

        $executor->executeRequest();
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
        $meta = $event->getExecutor()->getSocketMetaData($event->getSocket());
        echo '[' . $now->format('Y-m-d H:i:s') . '] ' . $event->getType() . ' on socket ' .
             $meta[RequestExecutorInterface::META_ADDRESS] . "\n";
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
        $context = $event->getContext();

        $event->setData($context['data']);
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
        $meta    = $event->getExecutor()->getSocketMetaData($event->getSocket());

        $context['response'] = $event->getResponse()->getData();

        echo $meta[RequestExecutorInterface::META_ADDRESS] . ' read ' .
             number_format(strlen($context['response']), 0, ',', ' ') . " bytes \n";

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
        $context  = $event->getContext();
        $socket   = $event->getSocket();
        $executor = $event->getExecutor();
        $meta     = $executor->getSocketMetaData($socket);

        $isTryingOneMoreTime = isset($context[ 'attempts' ]) &&
            $context[ 'attempts' ] - 1 > 0 &&
            $meta[ RequestExecutorInterface::META_REQUEST_COMPLETE ];
        echo "Packagist socket has disconnected\n";
        if ($isTryingOneMoreTime) {
            echo "Trying to get data one more time\n";

            $context['attempts'] -= 1;

            // automatically try one more time
            $executor->removeSocket($socket);
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
        echo "GitHub socket has disconnected\n";
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
        $meta = $event->getExecutor()->getSocketMetaData($event->getSocket());
        echo "Connected to Packagist: {$meta[RequestExecutorInterface::META_ADDRESS]}\n";
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
        $meta = $event->getExecutor()->getSocketMetaData($event->getSocket());
        echo "Connected to GitHub: {$meta[RequestExecutorInterface::META_ADDRESS]}\n";
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
        echo 'Exception during processing ' . $event->getOriginalEvent()->getType() . ': ' .
            $event->getException()->getMessage() . "\n";
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
        $meta = $event->getExecutor()->getSocketMetaData($event->getSocket());
        echo "Timeout happened on some socket {$meta[RequestExecutorInterface::META_ADDRESS]}\n";
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
            [ RequestExecutorInterface::META_ADDRESS => 'tls://packagist.org:443',
              RequestExecutorInterface::META_USER_CONTEXT => [
                  'data'     => "GET / HTTP/1.1\nHost: packagist.org\n\n",
                  'attempts' => $attempts
              ],

                RequestExecutorInterface::META_CONNECTION_TIMEOUT => $connectionTimeout,
                RequestExecutorInterface::META_IO_TIMEOUT         => $ioTimeout
            ]
        );

        $executor->addHandler([
            EventType::DISCONNECTED => [$this, 'onPackagistDisconnect'],
            EventType::CONNECTED    => [$this, 'onPackagistConnected'],
        ], $client);
    }
}
