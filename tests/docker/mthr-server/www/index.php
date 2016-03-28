<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$rootDir = getenv('ASYNC_SOCKETS_ROOT');
require_once $rootDir . '/demos/autoload.php';

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\SslHandshakeOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\SslDataFlushEventHandler;
use AsyncSockets\Socket\AsyncSocketFactory;

$factory = new AsyncSocketFactory();

$socket   = $factory->createSocket(
    AsyncSocketFactory::SOCKET_CLIENT,
    [ AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT => true ]
);
$executor = $factory->createRequestExecutor();
$result   = '';

$executor->socketBag()->addSocket(
    $socket,
    new SslHandshakeOperation(
        new WriteOperation(
            "GET / HTTP/1.1\r\n" .
            "Host: packagist.org\r\n" .
            "Connection: Keep-Alive\r\n" .
            "\r\n"
        )
    ),
    [
        RequestExecutorInterface::META_ADDRESS    => 'tcp://packagist.org:443',
        RequestExecutorInterface::META_IO_TIMEOUT => null,
    ],
    new SslDataFlushEventHandler(
        new CallbackEventHandler(
            [
                EventType::WRITE => function (WriteEvent $event) {
                    echo '<pre>' . $event->getSocket()->getStreamResource() . "\n</pre>";
                    $event->nextIs(new ReadOperation(new MarkerFramePicker('HTTP', "\r\n\r\n")));
                },
                EventType::READ => function (ReadEvent $event) use (&$result) {
                    $result = $event->getFrame()->getData();

                    $event->getExecutor()->socketBag()->postponeSocket(
                        $event->getSocket()
                    );
                    echo '<pre>ftell: ' . ftell($event->getSocket()->getStreamResource()) . "\n</pre>";
                },
                EventType::EXCEPTION => function (SocketExceptionEvent $event) use (&$result) {
                    $result = $event->getException()->getMessage();
                }
            ]
        )
    )
);

$executor->executeRequest();
echo '<pre>' . $result . '</pre>';
