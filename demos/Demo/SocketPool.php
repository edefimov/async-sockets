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
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\ConstantLimitationDecider;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\RequestExecutor\WriteOperation;
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
            ->setDescription('Demonstrates usage of LimitationDeciderInterface')
            ->addOption('total', 't', InputOption::VALUE_OPTIONAL, 'Total requests to execute', 256)
            ->addOption('concurrent', 'c', InputOption::VALUE_OPTIONAL, 'Amount of requests executed in time', 32)
            ->addOption(
                'address',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Destination address in form scheme://host:port',
                'tls://packagist.org:443'
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
        for ($i = 0; $i < $countSockets; $i++) {
            $client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $executor->getSocketBag()->addSocket(
                $client,
                new WriteOperation("GET / HTTP/1.1\nHost: packagist.org\n\n"),
                [
                    RequestExecutorInterface::META_ADDRESS      => $destination,
                    RequestExecutorInterface::META_OPERATION    => RequestExecutorInterface::OPERATION_WRITE,
                    RequestExecutorInterface::META_USER_CONTEXT => [
                        'index'  => $i + 1,
                        'output' => $output,
                    ]
                ]
            );
        }

        $executor->setLimitationDecider(new ConstantLimitationDecider($limitSockets));
        $executor->setEventInvocationHandler(
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
        $context = $event->getContext();
        $output  = $context['output'];
        /** @var OutputInterface $output */
        $message = $event->getException()->getMessage();
        $type    = $event->getOriginalEvent()->getType();
        $output->writeln(
            "<error>Exception during processing {$type}: {$message}</error>"
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

        $meta = $event->getExecutor()->getSocketBag()->getSocketMetaData($event->getSocket());
        $output->writeln(
            "<comment>Timeout happened on some socket {$meta[RequestExecutorInterface::META_ADDRESS]}</comment>"
        );
    }
}
