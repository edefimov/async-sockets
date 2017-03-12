<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\Frame\RawFramePicker;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SlowDownloadTestCommand
 */
class SlowDownloadTestCommand extends Command
{
    /**
     * Content length
     *
     * @var int
     */
    private $contentLength;

    /**
     * Received bytes
     *
     * @var int
     */
    private $receivedLength;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('test:slow-download')
            ->addOption(
                'min-speed',
                null,
                InputOption::VALUE_REQUIRED,
                'Min receive rate in bytes per second',
                1 * 1024 * 1024
            )->addOption(
                'duration',
                null,
                InputOption::VALUE_REQUIRED,
                'Min receive speed duration in seconds',
                30
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // http://download.thinkbroadband.com/1GB.zip
        $factory  = new AsyncSocketFactory();
        $socket   = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
        $executor = $factory->createRequestExecutor();
        $progress = null;
        $output->writeln('Starting download 1GB file');

        $executor->socketBag()->addSocket(
            $socket,
            new WriteOperation("GET /1GB.zip HTTP/1.1\nHost: download.thinkbroadband.co\n\n"),
            [
                RequestExecutorInterface::META_ADDRESS                    => "tcp://download.thinkbroadband.com:80",
                RequestExecutorInterface::META_CONNECTION_TIMEOUT         => 30,
                RequestExecutorInterface::META_IO_TIMEOUT                 => 30,
                RequestExecutorInterface::META_MIN_RECEIVE_SPEED          => (int) $input->getOption('min-speed'),
                RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION => (int) $input->getOption('duration'),
            ],
            new CallbackEventHandler(
                [
                    EventType::WRITE => function (WriteEvent $event) {
                        $event->nextIs(new ReadOperation(new MarkerFramePicker('HTTP', "\r\n\r\n", true)));
                    },
                    EventType::READ => function (
                        ReadEvent $event,
                        RequestExecutorInterface $executor,
                        SocketInterface $socket
                    ) use (&$progress, $output) {
                        if (!$this->contentLength) {
                            $this->contentLength  = $this->getContentLength($event->getFrame()->getData());
                            $this->receivedLength = 0;
                            $progress = new ProgressBar($output, $this->contentLength);
                            $progress->setFormat('%current%/%max% [%bar%] %percent:3s%% %speed%');
                            $progress->setRedrawFrequency(100000);
                            $event->nextIs(new ReadOperation(new RawFramePicker()));
                            return;
                        }

                        /** @var ProgressBar $progress */
                        $received              = strlen($event->getFrame()->getData());
                        $this->receivedLength += $received;
                        $progress->setMessage(
                            $executor
                                  ->socketBag()
                                  ->getSocketMetaData($socket)[RequestExecutorInterface::META_RECEIVE_SPEED],
                            'speed'
                        );
                        $progress->advance($received);
                        if ($this->receivedLength < $this->contentLength) {
                            $event->nextIs(new ReadOperation(new RawFramePicker()));
                        }
                    },
                    EventType::EXCEPTION => $this->getExceptionHandler($output)
                ]
            )
        );

        $timer = microtime(true);
        $executor->executeRequest();
        $timer = microtime(true) - $timer;
        $output->writeln(['', "Elapsed time: $timer s"]);
    }

    /**
     * Return exception handler
     *
     * @param OutputInterface $output Output interface
     *
     * @return callable
     */
    private function getExceptionHandler(OutputInterface $output)
    {
        return function (SocketExceptionEvent $event) use ($output) {
            $output->writeln('');
            $output->writeln(
                '<error>Exception: ' .
                $event->getException()->getMessage() . '</error>'
            );
        };
    }

    /**
     * Return implementation of FramePickerInterface to read content body
     *
     * @param string $headers Headers
     *
     * @return int
     * @throws \InvalidArgumentException
     */
    private function getContentLength($headers)
    {
        foreach (explode("\r\n", $headers) as $header) {
            if (strpos($header, 'Content-Length: ') === 0) {
                list(, $result) = explode(':', $header);
                return (int) $result;
            }
        }

        throw new \InvalidArgumentException('Can not resolve transfer type: ' . $headers);
    }
}
