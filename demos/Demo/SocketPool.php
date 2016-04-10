<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
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
use AsyncSockets\RequestExecutor\ConstantLimitationSolver;
use AsyncSockets\RequestExecutor\RemoveFinishedSocketsEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Operation\SslHandshakeOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\Socket\AsyncSocketFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SocketPool
 */
class SocketPool extends Command
{
    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this->setName('demo:socket_pool')
            ->setDescription('Demonstrates usage of LimitationSolverInterface')
            ->addOption('total', 't', InputOption::VALUE_OPTIONAL, 'Total requests to execute', 256)
            ->addOption('concurrent', 'c', InputOption::VALUE_OPTIONAL, 'Amount of requests executed in time', 32)
            ->addOption(
                'address',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Destination address in form scheme://host:port',
                'tcp://packagist.org:443'
            );
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $destination  = $input->getOption('address');
        $countSockets = (int) $input->getOption('total');
        $limitSockets = (int) $input->getOption('concurrent');
        $factory = new AsyncSocketFactory();

        $executor = $factory->createRequestExecutor();
        $host     = parse_url($destination, PHP_URL_HOST);
        for ($i = 0; $i < $countSockets; $i++) {
            $client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $executor->socketBag()->addSocket(
                $client,
                new SslHandshakeOperation(
                    new WriteOperation("GET / HTTP/1.1\nHost: {$host}\n\n"),
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                ),
                [
                    RequestExecutorInterface::META_ADDRESS      => $destination,
                    RequestExecutorInterface::META_USER_CONTEXT => [
                        'index'  => $i + 1,
                        'output' => $output,
                    ]
                ]
            );
        }

        $executor->withLimitationSolver(new ConstantLimitationSolver($limitSockets));
        $executor->withEventHandler(
            new RemoveFinishedSocketsEventHandler(
                new CallbackEventHandler(
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
                            function (ReadEvent $event) use ($output) {
                                $output->writeln('Read ' . strlen((string) $event->getFrame()) . ' bytes');
                            },
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
        $output  = $context['output'];
        /** @var OutputInterface $output */
        $output->writeln(
            "<info>[{$now->format('Y-m-d H:i:s')}] {$event->getType()} on socket {$context['index']}</info>"
        );
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
        $event->nextIsRead(new MarkerFramePicker(null, "\r\n\r\n"));
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
        $context = $event->getContext();
        $output  = $context['output'];
        /** @var OutputInterface $output */
        $message = $event->getException()->getMessage();
        $output->writeln(
            "<error>Exception: {$message}</error>"
        );
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
        $context = $event->getContext();
        $output  = $context['output'];
        /** @var OutputInterface $output */

        $meta = $event->getExecutor()->socketBag()->getSocketMetaData($event->getSocket());
        $output->writeln(
            "<comment>Timeout happened on some socket {$meta[RequestExecutorInterface::META_ADDRESS]}</comment>"
        );
    }
}
