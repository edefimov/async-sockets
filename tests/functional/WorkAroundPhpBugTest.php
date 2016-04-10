<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\Functional;

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\LibEventRequestExecutor;
use AsyncSockets\RequestExecutor\NativeRequestExecutor;
use AsyncSockets\RequestExecutor\Pipeline\BaseStageFactory;
use AsyncSockets\RequestExecutor\Pipeline\PipelineFactory;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\ClientSocket;

/**
 * Class WorkAroundPhpBugTest
 *
 * @link https://bugs.php.net/bug.php?id=52602
 */
class WorkAroundPhpBugTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testReadFromNetwork
     *
     * @param RequestExecutorInterface $executor Request executor engine
     * @param string[]                 $urls Array of urls to resource
     *
     * @dataProvider urlDataProvider
     * @group networking
     */
    public function testReadFromNetwork(RequestExecutorInterface $executor, array $urls)
    {
        foreach ($urls as $url) {
            $components = parse_url($url);
            $request    = "GET / HTTP/1.1\nHost: {$components['host']}:{$components['port']}\n\n";
            $socket     = new ClientSocket();

            $executor->socketBag()->addSocket(
                $socket,
                new WriteOperation($request),
                [
                    RequestExecutorInterface::META_ADDRESS => $url
                ]
            );
        }

        $mock = $this->getMock('Countable', ['count']);
        $mock->expects(self::exactly(count($urls)))->method('count');

        echo 'Processing with ' . get_class($executor) . " engine\n";
        $executor->withEventHandler(
            new CallbackEventHandler(
                [
                    EventType::WRITE => function (WriteEvent $event) {
                        $event->nextIsRead(new MarkerFramePicker(null, '</html>', false));
                    },
                    EventType::READ => function (ReadEvent $event) use ($mock) {
                        /** @var \Countable $mock */
                        $mock->count();
                        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
                        echo 'Processed ' . $meta[RequestExecutorInterface::META_ADDRESS] . "\n";
                        $output = strtolower($event->getFrame()->getData());
                        $meta   = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
                        self::assertTrue(
                            strpos($output, '</html>') !== false,
                            'Incomplete data were received for ' . $meta[RequestExecutorInterface::META_ADDRESS]
                        );
                    },
                    EventType::TIMEOUT => function (Event $event) {
                        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
                        self::fail('Timeout on socket ' . $meta[RequestExecutorInterface::META_ADDRESS]);
                    },
                    EventType::EXCEPTION => function (SocketExceptionEvent $event) {
                        self::fail('Exception ' . $event->getException()->getMessage());
                    }
                ]
            )
        );

        $executor->executeRequest();
    }

    /**
     * urlDataProvider
     *
     * @return array
     */
    public function urlDataProvider()
    {
        $urls = [
            'tcp://php.net:80',
            'tls://github.com:443',
            'tls://packagist.org:443',
            'tls://coveralls.io:443',
            'tcp://stackoverflow.com:80',
            'tls://google.com:443'
        ];

        $result = [
            [
                new NativeRequestExecutor(
                    new PipelineFactory(
                        new BaseStageFactory()
                    ),
                    new Configuration()
                ),
                $urls,
            ]
        ];

        if (extension_loaded('libevent')) {
            $result[] = [ new LibEventRequestExecutor(new BaseStageFactory(), new Configuration()), $urls ];
        }

        return $result;
    }
}
