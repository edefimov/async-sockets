<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Frame\MarkerFrame;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
use AsyncSockets\Socket\ClientSocket;

/**
 * Class WorkAroundPhpBugTest
 *
 * @link https://bugs.php.net/bug.php?id=52602
 */
class WorkAroundPhpBugTest extends \PHPUnit_Framework_TestCase
{
    /**
     * RequestExecutorInterface
     *
     * @var RequestExecutorInterface
     */
    private $executor;

    /**
     * testReadFromNetwork
     *
     * @param string[] $urls Array of urls to resource
     *
     * @return void
     * @dataProvider urlDataProvider
     * @group networking
     */
    public function testReadFromNetwork(array $urls)
    {
        foreach ($urls as $url) {
            $components = parse_url($url);
            $request    = "GET / HTTP/1.1\nHost: {$components['host']}:{$components['port']}\n\n";
            $socket     = new ClientSocket();

            $this->executor->getSocketBag()->addSocket(
                $socket,
                new WriteOperation($request),
                [
                    RequestExecutorInterface::META_ADDRESS => $url
                ]
            );
        }

        $this->executor->setEventInvocationHandler(
            new CallbackEventHandler(
                [
                    EventType::WRITE => function (WriteEvent $event) {
                        $event->nextIsRead();
                    },
                    EventType::READ => function (ReadEvent $event) {
                        $output = strtolower($event->getResponse()->getData());
                        $meta   = $event->getExecutor()->getSocketBag()->getSocketMetaData($event->getSocket());
                        self::assertTrue(
                            strpos($output, '</html>') !== false,
                            'Incomplete data were received for ' . $meta[RequestExecutorInterface::META_ADDRESS]
                        );
                    }
                ]
            )
        );

        $this->executor->executeRequest();
    }

    /**
     * urlDataProvider
     *
     * @return array
     */
    public function urlDataProvider()
    {
        return [
            [
                [
                    'tcp://php.net:80',
                    'tls://github.com:443',
                    'tls://packagist.org:443',
                    'tls://coveralls.io:443',
                    'tcp://stackoverflow.com:80',
                    'tls://google.com:443'
                ],
            ]
        ];
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->executor = new RequestExecutor();
    }
}
