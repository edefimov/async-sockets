<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\Component;

use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\SocketExceptionEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Operation\SslHandshakeOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use AsyncSockets\Socket\SocketInterface;
use Demo\Frame\SimpleHttpFramePicker;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ReadRemoteDataSynchronizer
 */
class ReadRemoteDataSynchronizer implements EventHandlerInterface
{
    /**
     * Data from remote socket
     *
     * @var mixed
     */
    private $data;

    /**
     * Write operation
     *
     * @var WriteOperation
     */
    private $writeOperation;

    /**
     * Target host
     *
     * @var string
     */
    private $destinationHost;

    /**
     * OutputInterface
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * ReadRemoteDataSynchronizer constructor.
     *
     * @param string          $destinationHost Destination host in form scheme://address:port
     * @param OutputInterface $output Output interface for messaging
     */
    public function __construct($destinationHost, OutputInterface $output)
    {
        $this->writeOperation = new WriteOperation(
            "GET / HTTP/1.1\r\n" .
            'Host: ' . parse_url($destinationHost, PHP_URL_HOST) . "\r\n" .
            "Connection: Keep-Alive\r\n" .
            "\r\n"
        );
        $this->destinationHost = $destinationHost;
        $this->output          = $output;
    }

    /**
     * Return WriteOperation
     *
     * @return WriteOperation
     */
    public function getWriteOperation()
    {
        return $this->writeOperation;
    }

    /**
     * Reset internal state
     *
     * @return void
     */
    public function reset()
    {
        $this->data = null;
    }

    /**
     * Check whether this object is resolved
     *
     * @return bool
     */
    public function isResolved()
    {
        return $this->data !== null;
    }

    /**
     * Return Data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Clear data
     *
     * @return void
     */
    public function clearData()
    {
        $this->data = null;
    }

    /** {@inheritdoc} */
    public function invokeEvent(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        switch ($event->getType()) {
            case EventType::INITIALIZE:
                $this->onInitialize($event, $executor, $socket, $context);
                break;
            case EventType::DATA_ALERT:
                $this->output->writeln('<error>Some new data arrived while we don\'t have handler</error>');
                break;
            case EventType::READ:
                /** @var ReadEvent $event */
                $this->onRead($event);
                break;
            case EventType::WRITE:
                /** @var WriteEvent $event */
                $this->onWrite($event);
                break;
            case EventType::FINALIZE:
                $this->onFinalize($event, $executor, $socket, $context);
                break;
            case EventType::TIMEOUT:
                $this->output->writeln('<comment>Timeout on persistent socket</comment>');
                break;
            case EventType::EXCEPTION:
                /** @var SocketExceptionEvent $event */
                $this->onException($event);
                break;
        }
    }

    /**
     * Exception event
     *
     * @param SocketExceptionEvent $event Exception event object
     *
     * @return void
     */
    private function onException(SocketExceptionEvent $event)
    {
        $this->output->writeln('<error>' . $event->getException()->getMessage() . '</error>');
    }

    /**
     * Initialize event
     *
     * @param Event                    $event    Event object
     * @param RequestExecutorInterface $executor Request executor fired an event
     * @param SocketInterface          $socket   Socket connected with event
     * @param ExecutionContext         $context  Global data context
     *
     * @return void
     */
    private function onInitialize(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        $this->output->writeln('<info>Initialized persistent socket</info>');
        $writeOperation = new SslHandshakeOperation(
            $this->writeOperation,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        $socketBag = $executor->socketBag();
        $socketBag->setSocketOperation($socket, $writeOperation);
        $socketBag->setSocketMetaData(
            $socket,
            [
                RequestExecutorInterface::META_ADDRESS            => $this->destinationHost,
                RequestExecutorInterface::META_CONNECTION_TIMEOUT => 60,
                RequestExecutorInterface::META_IO_TIMEOUT         => null,
            ]
        );
    }

    /**
     * Finalize event handler
     *
     * @param Event                    $event    Event object
     * @param RequestExecutorInterface $executor Request executor fired an event
     * @param SocketInterface          $socket   Socket connected with event
     * @param ExecutionContext         $context  Global data context
     *
     * @return void
     */
    private function onFinalize(
        Event $event,
        RequestExecutorInterface $executor,
        SocketInterface $socket,
        ExecutionContext $context
    ) {
        $this->output->writeln('<info>Persistent socket finalized</info>');
        $executor->socketBag()->removeSocket($socket);
    }

    /**
     * Read event
     *
     * @param ReadEvent $event Read event
     *
     * @return void
     */
    private function onRead(ReadEvent $event)
    {
        $this->data = $event->getFrame()->getData();
        $event->nextOperationNotRequired();
    }

    /**
     * Write event handler
     *
     * @param WriteEvent $event Write event
     *
     * @return void
     */
    private function onWrite(WriteEvent $event)
    {
        $event->nextIsRead(new SimpleHttpFramePicker());
    }
}
