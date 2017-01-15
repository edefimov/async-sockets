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
 * Class SlowUploadTestCommand
 */
class SlowUploadTestCommand extends Command
{
    /**
     * Received bytes
     *
     * @var int
     */
    private $transferedLength;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('test:slow-upload')
            ->addOption(
                'min-speed',
                null,
                InputOption::VALUE_REQUIRED,
                'Min send rate in bytes per second',
                1 * 1024 * 1024
            )->addOption(
                'duration',
                null,
                InputOption::VALUE_REQUIRED,
                'Min send speed duration in seconds',
                5
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory  = new AsyncSocketFactory();
        $socket   = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
        $executor = $factory->createRequestExecutor();
        $progress = null;
        $output->writeln('Starting upload 5MB file');

        $dataSize    = 5 * 1024 * 1024;
        $boundary    = sha1(microtime(true));
        $frameEnd    = "\r\n--{$boundary}--\r\n";
        $dirOnServer = sha1('async-sockets-tests');
        $fileHeader  = "--{$boundary}\r\n" .
                       "Content-Disposition: form-data; name=\"testFile\"; filename=\"test.txt\"\r\n" .
                       "Content-Type: application/octet-stream\r\n" .
                       "Content-Transfer-Encoding: binary\r\n\r\n";

        $contentLength = strlen($fileHeader) + $dataSize + strlen($frameEnd);

        $executor->socketBag()->addSocket(
            $socket,
            new WriteOperation("POST /post.php?dir={$dirOnServer} HTTP/1.0\r\n" .
                               "Host: posttestserver.com\r\n" .
                               "Content-Type: multipart/form-data; boundary={$boundary}\r\n" .
                               "Content-Length: {$contentLength}\r\n\r\n" .
                               $fileHeader),
            [
                RequestExecutorInterface::META_ADDRESS                    => "tls://posttestserver.com:443",
                RequestExecutorInterface::META_CONNECTION_TIMEOUT         => 30,
                RequestExecutorInterface::META_IO_TIMEOUT                 => 30,
                RequestExecutorInterface::META_MIN_SEND_SPEED             => (int) $input->getOption('min-speed'),
                RequestExecutorInterface::META_MIN_SEND_SPEED_DURATION    => (int) $input->getOption('duration'),
                RequestExecutorInterface::META_MIN_RECEIVE_SPEED          => 1024 * 1024 * 1024,
                RequestExecutorInterface::META_MIN_RECEIVE_SPEED_DURATION => 1,
            ],
            new CallbackEventHandler(
                [
                    EventType::READ => function (ReadEvent $event) use ($output) {
                        $output->writeln($event->getFrame()->getData());
                    },
                    EventType::WRITE => function (WriteEvent $event) use (&$progress, $output, $dataSize, $frameEnd) {
                        if (!$progress) {
                            $this->transferedLength = 0;
                            $progress               = new ProgressBar($output, $dataSize);
                            $progress->setFormat('%current%/%max% [%bar%] %percent:3s%% %speed%');
                            $progress->setRedrawFrequency(100000);
                            $event->nextIs(new WriteOperation());
                            return;
                        }

                        /** @var ProgressBar $progress */
                        $transferLength = 8192;
                        if ($this->transferedLength + $transferLength >= $dataSize) {
                            $transferLength = $dataSize - $this->transferedLength;
                            $data           = str_repeat('x', $transferLength);
                            $data          .= $frameEnd;
                        } else {
                            $data = str_repeat('x', $transferLength);
                        }

                        $event->getOperation()->setData($data);

                        $this->transferedLength += $transferLength;
                        $progress->setMessage(
                            $event->getExecutor()
                                  ->socketBag()
                                  ->getSocketMetaData($event->getSocket())[RequestExecutorInterface::META_SEND_SPEED],
                            'speed'
                        );
                        $progress->advance($transferLength);
                        if ($this->transferedLength < $dataSize) {
                            $event->nextIs(new WriteOperation());
                        } else {
                            $event->nextIs(new ReadOperation(new MarkerFramePicker(null, "\r\n\r\n")));
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
