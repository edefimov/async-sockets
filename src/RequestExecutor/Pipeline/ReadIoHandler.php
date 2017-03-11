<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\Event\AcceptEvent;
use AsyncSockets\Event\ReadEvent;
use AsyncSockets\Exception\AcceptException;
use AsyncSockets\Frame\AcceptedFrame;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\Operation\OperationInterface;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\EventHandlerInterface;
use AsyncSockets\RequestExecutor\ExecutionContext;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class ReadIoHandler
 */
class ReadIoHandler extends AbstractOobHandler implements FramePickerInterface
{
    /**
     * Amount of bytes read by last operation
     *
     * @var int
     */
    private $bytesRead;

    /**
     * Actual frame picker
     *
     * @var FramePickerInterface
     */
    private $realFramePicker;

    /** {@inheritdoc} */
    public function supports(OperationInterface $operation)
    {
        return $operation instanceof ReadOperation;
    }

    /** {@inheritdoc} */
    protected function handleOperation(
        RequestDescriptor $descriptor,
        RequestExecutorInterface $executor,
        EventHandlerInterface $eventHandler,
        ExecutionContext $executionContext
    ) {
        /** @var ReadOperation $operation */
        $operation = $descriptor->getOperation();
        $socket    = $descriptor->getSocket();

        $meta    = $executor->socketBag()->getSocketMetaData($socket);
        $context = $meta[RequestExecutorInterface::META_USER_CONTEXT];
        $result  = null;

        $this->bytesRead       = 0;
        $this->realFramePicker = $operation->getFramePicker();

        try {
            $response = $socket->read($this);
            switch (true) {
                case $response instanceof PartialFrame:
                    $result = $operation;
                    break;
                case $response instanceof AcceptedFrame:
                    $event = new AcceptEvent(
                        $executor,
                        $socket,
                        $context,
                        $response->getClientSocket(),
                        $response->getRemoteAddress()
                    );

                    $eventHandler->invokeEvent($event, $executor, $socket, $executionContext);
                    $result = new ReadOperation();
                    break;
                default:
                    $event = new ReadEvent(
                        $executor,
                        $socket,
                        $context,
                        $response,
                        false
                    );

                    $eventHandler->invokeEvent($event, $executor, $socket, $executionContext);
                    $result = $event->getNextOperation();
                    break;
            }
        } catch (AcceptException $e) {
            $result = new ReadOperation();
        } catch (\Exception $e) {
            $this->appendReadBytes($descriptor, $this->bytesRead);
            unset($this->realFramePicker, $this->bytesRead);
            throw $e;
        }

        $this->appendReadBytes($descriptor, $this->bytesRead);
        unset($this->realFramePicker, $this->bytesRead);

        return $result;
    }

    /**
     * Append given mount of read bytes to descriptor
     *
     * @param RequestDescriptor $descriptor The descriptor
     * @param int               $bytesRead Amount of read bytes
     *
     * @return void
     */
    private function appendReadBytes(RequestDescriptor $descriptor, $bytesRead)
    {
        $this->handleTransferCounter(RequestDescriptor::COUNTER_RECV_MIN_RATE, $descriptor, $bytesRead);
    }

    /**
     * {@inheritDoc}
     */
    public function isEof()
    {
        return $this->realFramePicker->isEof();
    }

    /**
     * {@inheritDoc}
     */
    public function pickUpData($chunk, $remoteAddress)
    {
        $this->bytesRead += strlen($chunk);
        return $this->realFramePicker->pickUpData($chunk, $remoteAddress);
    }

    /**
     * {@inheritDoc}
     */
    public function createFrame()
    {
        return $this->realFramePicker->createFrame();
    }
}
