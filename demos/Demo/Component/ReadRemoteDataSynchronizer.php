<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\Component;

use AsyncSockets\Event\SocketExceptionEvent;
use Demo\Frame\SimpleHttpFramePicker;
use AsyncSockets\Event\Event;
use AsyncSockets\Event\EventType;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Event\WriteEvent;
use AsyncSockets\Operation\SslHandshakeOperation;
use AsyncSockets\Operation\WriteOperation;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
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
    public function invokeEvent(Event $event)
    {
        switch ($event->getType()) {
            case EventType::INITIALIZE:
                $this->onInitialize($event);
                break;
            case EventType::DATA_ARRIVED:
                $this->output->writeln('<error>Some new data arrived while we don\'t have handler</error>');
                break;
            case EventType::READ:
                $this->onRead($event);
                break;
            case EventType::WRITE:
                $this->onWrite($event);
                break;
            case EventType::FINALIZE:
                $this->onFinalize($event);
                break;
            case EventType::TIMEOUT:
                $this->output->writeln('<comment>Timeout on persistent socket</comment>');
                break;
            case EventType::EXCEPTION:
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
     * @param Event $event Event object
     *
     * @return void
     */
    private function onInitialize(Event $event)
    {
        $this->output->writeln('<info>Initialized persistent socket</info>');
        $writeOperation = new SslHandshakeOperation(
            $this->writeOperation,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        $socketBag = $event->getExecutor()->socketBag();
        $socketBag->setSocketOperation($event->getSocket(), $writeOperation);
        $socketBag->setSocketMetaData(
            $event->getSocket(),
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
     * @param Event $event Event object
     *
     * @return void
     */
    private function onFinalize(Event $event)
    {
        $this->output->writeln('<info>Persistent socket finalized</info>');
        $event->getExecutor()->socketBag()->removeSocket($event->getSocket());
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
