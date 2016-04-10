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

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Frame\MarkerFramePicker;
use AsyncSockets\RequestExecutor\CallbackEventHandler;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\RemoveFinishedSocketsEventHandler;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\AsyncSocketFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SimpleServer
 */
class SimpleServer extends Command
{
    /**
     * Handlers for client
     *
     * @var EventHandlerInterface
     */
    private $clientHandlers;

    /**
     * Library root directory
     *
     * @var string
     */
    private $rootDir;

    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('demo:simple_server')
            ->setDescription('Demonstrates example usage of server sockets')
            ->setHelp('Starts HTTP server on passed host and port and allows to serve library files via browser')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host name to bind', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen', '10032')
            ;
    }

    /**
     * Extract path from request
     *
     * @param string $request Request from browser
     *
     * @return string
     */
    private function extractPath($request)
    {
        $components = explode(' ', $request);
        $result     = '';
        if (isset($components[1])) {
            $result = parse_url($components[1], PHP_URL_PATH);
        }

        return $result ?: '';
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->rootDir = realpath(__DIR__ . '/../..');
        $factory       = new AsyncSocketFactory();
        $serverSocket  = $factory->createSocket(AsyncSocketFactory::SOCKET_SERVER);
        $executor      = $factory->createRequestExecutor();

        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');
        $output->writeln("<info>Starting HTTP server on {$host}:{$port}</info>");
        $output->writeln('<comment>Press CTRL+C to exit</comment>');
        $executor->socketBag()->addSocket(
            $serverSocket,
            new ReadOperation(),
            [
                RequestExecutorInterface::META_ADDRESS            => "tcp://{$host}:{$port}",
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => RequestExecutorInterface::WAIT_FOREVER,
                RequestExecutorInterface::META_IO_TIMEOUT         => RequestExecutorInterface::WAIT_FOREVER,
            ],
            new CallbackEventHandler(
                [
                    EventType::ACCEPT => function (AcceptEvent $event) use ($output) {
                        $output->writeln("<info>Incoming connection from {$event->getRemoteAddress()}</info>");
                        $event->getExecutor()->socketBag()->addSocket(
                            $event->getClientSocket(),
                            new ReadOperation(
                                new MarkerFramePicker(null, "\r\n\r\n")
                            ),
                            [ ],
                            $this->getAcceptedClientHandlers($output)
                        );
                    },
                    EventType::EXCEPTION => $this->getExceptionHandler($output)
                ]
            )
        );

        $executor->executeRequest();
    }

    /**
     * Return handler for clients
     *
     * @param OutputInterface $output Output interface
     *
     * @return EventHandlerInterface
     */
    private function getAcceptedClientHandlers(OutputInterface $output)
    {
        if (!$this->clientHandlers) {
            $this->clientHandlers = new RemoveFinishedSocketsEventHandler(
                new CallbackEventHandler(
                    [
                        EventType::READ => function (ReadEvent $event) use ($output) {
                            $frame    = $event->getFrame();
                            $request  = $frame->getData();
                            $path     = $this->extractPath($request);
                            $response = $this->generateResponse($path);
                            $output->writeln("<comment>Received request from {$frame->getRemoteAddress()}</comment>");

                            $event->nextIsWrite(
                                "HTTP/1.1 200 OK \r\n" .
                                "Content-Type: text/html;charset=utf-8\r\n" .
                                'Content-Length: ' . strlen($response) . "\r\n\r\n" .
                                $response
                            );
                        },
                        EventType::DISCONNECTED => function () use ($output) {
                            $output->writeln('Client disconnected');
                        },
                        EventType::EXCEPTION => $this->getExceptionHandler($output)
                    ]
                )
            );
        }

        return $this->clientHandlers;
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
            $output->writeln(
                '<error>Exception: ' .
                $event->getException()->getMessage() . '</error>'
            );
        };
    }

    /**
     * Generate response for file name
     *
     * @param string $path Path name
     *
     * @return string
     */
    private function generateResponse($path)
    {
        $targetPath = realpath($this->rootDir . $path);

        if (!$targetPath || $this->isOutOfRoot($targetPath)) {
            return 'Malformed path provided';
        }

        if (is_dir($targetPath)) {
            return $this->generateDirectoryIndex($targetPath);
        }

        if (is_file($targetPath)) {
            return $this->generateFile($targetPath);
        }

        return '';
    }

    /**
     * Generate directory index
     *
     * @param string $fullPath Full path to target file
     *
     * @return string
     */
    private function generateDirectoryIndex($fullPath)
    {
        $info = pathinfo($fullPath);
        $url  = $this->getUrlForPath($info['dirname']);

        $items = [
            "<li><a href=\"{$url}\">..</a></li>"
        ];

        $files = glob($fullPath . '/*');
        natsort($files);
        foreach ($files as $filename) {
            $url = $this->getUrlForPath($filename);

            $items[] = "<li><a href=\"{$url}\">" . basename($filename) . '</a></li>';
        }

        return '<html><body><ul>' . implode('', $items) . '</ul></body></html>';
    }

    /**
     * Generate file contents
     *
     * @param string $fileName File name
     *
     * @return string
     */
    private function generateFile($fileName)
    {
        $info = pathinfo($fileName);
        $url  = $this->getUrlForPath($info['dirname']);

        if ($info['extension'] === 'php') {
            $content = highlight_file($fileName, true);
        } else {
            $content = '<pre>' . htmlspecialchars(file_get_contents($fileName)) . '</pre>';
        }

        $content = "<p><a href=\"{$url}\">Return back to folder</a></p><p>{$content}</p>";
        return '<html><body>' . $content . '</body></html>';
    }

    /**
     * Return url to given path
     *
     * @param string $path Path to get url for
     *
     * @return string
     */
    private function getUrlForPath($path)
    {
        $url = str_replace($this->rootDir, '', $path);

        if (!$url || $this->isOutOfRoot($path)) {
            $url = '/';
        }

        return $url;
    }

    /**
     * Checks whether given path is inside of library
     *
     * @param string $path Path name
     *
     * @return bool
     */
    private function isOutOfRoot($path)
    {
        return strpos($path, $this->rootDir) !== 0;
    }
}
