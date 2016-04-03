<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Exception\ConnectionException;
use AsyncSockets\Exception\FrameSocketException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\PartialFrame;

/**
 * Class AbstractClientIo
 */
abstract class AbstractClientIo extends AbstractIo
{
    /**
     * Disconnected state
     */
    const STATE_DISCONNECTED = 0;

    /**
     * Connected state
     */
    const STATE_CONNECTED = 1;

    /**
     * Unhandled portion of data at the end of frame
     *
     * @var string
     */
    private $unhandledData = '';

    /**
     * Socket state
     *
     * @var int
     */
    private $state = self::STATE_DISCONNECTED;

    /**
     * Amount of attempts to write data before treat request as failed
     *
     * @var int
     */
    private $writeAttempts = self::IO_ATTEMPTS;

    /**
     * Read raw data from network into given picker
     *
     * @param FramePickerInterface $picker Frame picker
     *
     * @return string Data after end of frame
     */
    abstract protected function readRawDataIntoPicker(FramePickerInterface $picker);

    /**
     * Write data to socket
     *
     * @param string $data Data to write
     *
     * @return int Number of written bytes
     */
    abstract protected function writeRawData($data);

    /**
     * Check whether given socket resource is connected
     *
     * @return bool
     */
    abstract protected function isConnected();

    /**
     * Return true if frame can be collected in nearest future, false otherwise
     *
     * @return bool
     */
    abstract protected function canReachFrame();

    /**
     * Return remote socket ip address
     *
     * @return string
     */
    abstract protected function getRemoteAddress();

    /** {@inheritdoc} */
    final public function read(FramePickerInterface $picker)
    {
        $this->setConnectedState();
        $isEndOfFrameReached = $this->handleUnreadData($picker);
        if (!$isEndOfFrameReached) {
            $this->unhandledData = $this->readRawDataIntoPicker($picker);

            $isEndOfFrameReached = $picker->isEof();
            if (!$isEndOfFrameReached && !$this->canReachFrame()) {
                throw new FrameSocketException($picker, $this->socket, 'Failed to receive desired frame.');
            }
        }

        $frame = $picker->createFrame();
        if (!$isEndOfFrameReached) {
            $frame = new PartialFrame($frame);
        }

        return $frame;
    }

    /** {@inheritdoc} */
    final public function write($data)
    {
        $this->setConnectedState();

        $result              = $this->writeRawData($data);
        $this->writeAttempts = $result > 0 ? self::IO_ATTEMPTS : $this->writeAttempts - 1;

        $this->throwNetworkSocketExceptionIf(
            !$this->writeAttempts && $result !== strlen($data),
            'Failed to send data.'
        );

        return $result;
    }

    /**
     * Verify, that we are in connected state
     *
     * @return void
     * @throws ConnectionException
     */
    private function setConnectedState()
    {
        $resource = $this->socket->getStreamResource();
        if (!is_resource($resource)) {
            $message = $this->state === self::STATE_CONNECTED ?
                'Connection was unexpectedly closed.' :
                'Can not start io operation on uninitialized socket.';
            throw new ConnectionException($this->socket, $message);
        }

        if ($this->state !== self::STATE_CONNECTED) {
            if (!$this->isConnected()) {
                throw new ConnectionException($this->socket, 'Connection refused.');
            }

            $this->state = self::STATE_CONNECTED;
        }
    }

    /**
     * Checks that we are in connected state
     *
     * @param string $message Message to pass in exception
     *
     * @return void
     * @throws NetworkSocketException
     */
    final protected function throwExceptionIfNotConnected($message)
    {
        $this->throwNetworkSocketExceptionIf(
            !$this->isConnected(),
            $message
        );
    }

    /**
     * Read unhandled data if there is something from the previous operation
     *
     * @param FramePickerInterface $picker Frame picker to use
     *
     * @return bool Flag whether it is the end of the frame
     */
    private function handleUnreadData(FramePickerInterface $picker)
    {
        if ($this->unhandledData) {
            $this->unhandledData = $picker->pickUpData($this->unhandledData, $this->getRemoteAddress());
        }

        return $picker->isEof();
    }
}
