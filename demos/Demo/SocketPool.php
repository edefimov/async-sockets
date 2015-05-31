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
use AsyncSockets\RequestExecutor\ConstantLimitationDecider;
use AsyncSockets\RequestExecutor\EventInvocationHandlerBag;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
use AsyncSockets\Socket\AsyncSocketFactory;

/**
 * Class SocketPool
 */
class SocketPool
{
    /**
     * Main
     *
     * @return void
     */
    public function main()
    {
        $destination  = 'tls://packagist.org:443';
        $countSockets = 256;
        $limitSockets = 32;
        $factory = new AsyncSocketFactory();

        $executor = $factory->createRequestExecutor();
        for ($i = 0; $i < $countSockets; $i++) {
            $client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $executor->getSocketBag()->addSocket(
                $client,
                new WriteOperation("GET / HTTP/1.1\nHost: packagist.org\n\n"),
                [
                    RequestExecutorInterface::META_ADDRESS      => $destination,
                    RequestExecutorInterface::META_OPERATION    => RequestExecutorInterface::OPERATION_WRITE,
                    RequestExecutorInterface::META_USER_CONTEXT => [
                        'index' => $i + 1
                    ]
                ]
            );
        }

        $executor->setLimitationDecider(new ConstantLimitationDecider($limitSockets));
        $executor->setEventInvocationHandler(
            new EventInvocationHandlerBag(
                [
                    EventType::DISCONNECTED => [
                        [ $this, 'logEvent' ],
                    ],
                    EventType::CONNECTED    => [
                        [ $this, 'logEvent' ],
                    ],
                    EventType::WRITE        => [
                        [ $this, 'logEvent' ],
                        [ $this, 'onWrite' ],
                    ],
                    EventType::READ         => [
                        [ $this, 'logEvent' ],
                        [ $this, 'onRead' ],
                    ],
                    EventType::EXCEPTION    => [
                        [ $this, 'logEvent' ],
                        [ $this, 'onException' ],
                    ],
                    EventType::TIMEOUT      => [
                        [ $this, 'logEvent' ],
                        [ $this, 'onTimeout' ],
                    ],
                ]
            )
        );

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
        $now     = new \DateTime();
        $context = $event->getContext();
        echo '[' . $now->format('Y-m-d H:i:s') . '] ' . $event->getType() . ' on socket ' . $context['index'] . "\n";
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
        $event->nextOperationNotRequired();
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
        $meta = $event->getExecutor()->getSocketBag()->getSocketMetaData($event->getSocket());
        echo "Timeout happened on some socket {$meta[RequestExecutorInterface::META_ADDRESS]}\n";
    }
}
