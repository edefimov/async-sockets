<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\Functional;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
use Demo\Frame\SimpleHttpFramePicker;

/**
 * Class PersistentSocketTest
 */
class PersistentSocketTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testThatResourceIdentifierWillBeKept
     *
     * @return void
     */
    public function testThatResourceIdentifierWillBeKept()
    {
        $factory    = new AsyncSocketFactory();
        $iterations = 3;

        $resources = [];
        for (; $iterations; $iterations--) {
            $socket   = $factory->createSocket(
                AsyncSocketFactory::SOCKET_CLIENT,
                [ AsyncSocketFactory::SOCKET_OPTION_IS_PERSISTENT => true ]
            );
            $executor = $factory->createRequestExecutor();

            $executor->socketBag()->addSocket(
                $socket,
                new WriteOperation(
                    "GET / HTTP/1.1\r\n" .
                    "Host: packagist.org\r\n" .
                    "Connection: Keep-Alive\r\n" .
                    "\r\n"
                ),
                [
                    RequestExecutorInterface::META_ADDRESS    => 'tls://packagist.org:443',
                    RequestExecutorInterface::META_IO_TIMEOUT => null,
                ],
                new CallbackEventHandler(
                    [
                        EventType::WRITE     => [ $this, 'writeEventHandler' ],
                        EventType::READ      => [ $this, 'readEventHandler' ],
                        EventType::EXCEPTION => [ $this, 'exceptionEventHandler' ],
                    ]
                )
            );

            $executor->executeRequest();
            $socket->open('tls://packagist.org:443');
            $resources[] = $socket->getStreamResource();
        }

        self::assertCount(1, array_unique($resources), 'Different resources were returned during iterations');
    }

    /**
     * writeEventHandler
     *
     * @param WriteEvent $event Event
     *
     * @return void
     */
    public function writeEventHandler(WriteEvent $event)
    {
        $event->nextIs(new ReadOperation(new SimpleHttpFramePicker()));
    }

    /**
     * readEventHandler
     *
     * @param ReadEvent                $event    Event
     * @param RequestExecutorInterface $executor Request executor
     * @param SocketInterface          $socket   Socket object
     *
     * @return void
     */
    public function readEventHandler(ReadEvent $event, RequestExecutorInterface $executor, SocketInterface $socket)
    {
        $executor->socketBag()->forgetSocket($socket);
    }

    /**
     * exceptionHandler
     *
     * @param SocketExceptionEvent $event Event
     *
     * @return void
     */
    public function exceptionEventHandler(SocketExceptionEvent $event)
    {
        self::fail($event->getException()->getMessage());
    }
}
