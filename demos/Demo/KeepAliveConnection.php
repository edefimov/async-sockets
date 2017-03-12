<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
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
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\RemoveFinishedSocketsEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use AsyncSockets\Socket\SocketInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class KeepAliveConnection
 */
class KeepAliveConnection extends Command
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
        $this->setName('demo:keep_alive_onnection')
            ->setDescription('Demonstrates usage of keep-alive socket connections');
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $factory = new AsyncSocketFactory();

        $client   = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
        $executor = $factory->createRequestExecutor();

        $executor->socketBag()->addSocket(
            $client,
            new WriteOperation("GET / HTTP/1.1\nHost: github.com\nConnection: Keep-Alive\n\n"),
            [
                RequestExecutorInterface::META_ADDRESS    => 'tls://github.com:443',
                RequestExecutorInterface::META_KEEP_ALIVE => true,
            ]
        );

        $handler = new CallbackEventHandler(
            [
                EventType::INITIALIZE   => [ $this, 'logEvent' ],
                EventType::FINALIZE     => [ $this, 'logEvent' ],
                EventType::CONNECTED    => [ $this, 'logEvent' ],
                EventType::DISCONNECTED => [ $this, 'logEvent' ],
                EventType::WRITE        => [ [ $this, 'logEvent' ], [ $this, 'onWrite' ] ],
                EventType::READ         => [ [ $this, 'logEvent' ], [ $this, 'onRead' ] ],
                EventType::EXCEPTION    => [ $this, 'onException' ],
                EventType::TIMEOUT      => [ $this, 'onTimeout' ],
            ]
        );
        $handler = new RemoveFinishedSocketsEventHandler($handler);

        $executor->withEventHandler($handler);
        $executor->executeRequest();

        $executor->socketBag()->addSocket(
            $client,
            new WriteOperation("GET / HTTP/1.1\nHost: github.com\nConnection: Keep-Alive\n\n"),
            [
                RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
                RequestExecutorInterface::META_KEEP_ALIVE => true,
            ]
        );
        $executor->executeRequest();

        $this->output = null;
    }

    /**
     * Log event
     *
     * @param Event                    $event    Event object
     * @param RequestExecutorInterface $executor Request executor
     * @param SocketInterface          $socket   Socket object
     *
     * @return void
     */
    public function logEvent(Event $event, RequestExecutorInterface $executor, SocketInterface $socket)
    {
        $now  = new \DateTime();
        $meta = $executor->socketBag()->getSocketMetaData($socket);
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
     * @param ReadEvent                $event    Event object
     * @param RequestExecutorInterface $executor Request executor
     * @param SocketInterface          $socket   Socket object
     *
     * @return void
     */
    public function onRead(ReadEvent $event, RequestExecutorInterface $executor, SocketInterface $socket)
    {
        $context = $event->getContext();
        $meta    = $executor->socketBag()->getSocketMetaData($socket);

        $context['response'] = $event->getFrame()->getData();

        $this->output->writeln("<info>{$meta[RequestExecutorInterface::META_ADDRESS]}  read " .
             number_format(strlen($context['response']), 0, ',', ' ') . ' bytes</info>');

        $executor->socketBag()->setSocketMetaData(
            $socket,
            RequestExecutorInterface::META_USER_CONTEXT,
            $context
        );

        $executor->socketBag()->forgetSocket($socket);
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
        $this->output->writeln('<error>Exception occured: ' .
            $event->getException()->getMessage() . '</error>');
    }

    /**
     * Timeout event
     *
     * @param Event                    $event    Event object
     * @param RequestExecutorInterface $executor Request executor
     * @param SocketInterface          $socket   Socket object
     *
     * @return void
     */
    public function onTimeout(Event $event, RequestExecutorInterface $executor, SocketInterface $socket)
    {
        $meta = $executor->socketBag()->getSocketMetaData($socket);
        $this->output->writeln(
            "<comment>Timeout happened on some socket {$meta[RequestExecutorInterface::META_ADDRESS]}</comment>"
        );
    }
}
