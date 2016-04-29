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
use AsyncSockets\Exception\FrameException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Exception\UnsupportedOperationException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\PartialFrame;
use AsyncSockets\Socket\SocketInterface;

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
     * Maximum amount of OOB data length (0 - not supported)
     *
     * @var int
     */
    private $maxOobPacketLength;

    /**
     * AbstractClientIo constructor.
     *
     * @param SocketInterface $socket Socket object
     * @param int             $maxOobPacketLength Maximum amount of OOB data length (0 - not supported)
     */
    public function __construct(SocketInterface $socket, $maxOobPacketLength)
    {
        parent::__construct($socket);
        $this->maxOobPacketLength = $maxOobPacketLength;
    }

    /**
     * Read raw data from network into given picker
     *
     * @param FramePickerInterface $picker Frame picker
     * @param bool                 $isOutOfBand Flag if these are out of band data
     *
     * @return string Data after end of frame
     */
    abstract protected function readRawDataIntoPicker(FramePickerInterface $picker, $isOutOfBand);

    /**
     * Write data to socket
     *
     * @param string $data Data to write
     * @param bool $isOutOfBand Flag if data are out of band
     *
     * @return int Number of written bytes
     */
    abstract protected function writeRawData($data, $isOutOfBand);

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
    final public function read(FramePickerInterface $picker, Context $context, $isOutOfBand)
    {
        $this->setConnectedState();
        $this->verifyOobData($isOutOfBand, null);

        $isEndOfFrameReached = $this->handleUnreadData($picker, $context, $isOutOfBand);
        if (!$isEndOfFrameReached) {
            $context->setUnreadData(
                $this->readRawDataIntoPicker($picker, $isOutOfBand),
                $isOutOfBand
            );

            $isEndOfFrameReached = $picker->isEof();
            if (!$isEndOfFrameReached && !$this->canReachFrame()) {
                throw new FrameException($picker, $this->socket, 'Failed to receive desired frame.');
            }
        }

        $frame = $picker->createFrame();
        if (!$isEndOfFrameReached) {
            $frame = new PartialFrame($frame);
        }

        return $frame;
    }

    /** {@inheritdoc} */
    final public function write($data, Context $context, $isOutOfBand)
    {
        $this->setConnectedState();
        $this->verifyOobData($isOutOfBand, $data);

        $result              = $this->writeRawData($data, $isOutOfBand);
        $this->writeAttempts = $result > 0 ? self::IO_ATTEMPTS : $this->writeAttempts - 1;

        $this->throwNetworkSocketExceptionIf(
            !$this->writeAttempts && $result !== strlen($data),
            'Failed to send data.'
        );

        return $result;
    }

    /**
     * Verifies given data according to OOB rules
     *
     * @param bool   $isOutOfBand Flag if data are out of band
     * @param string $data Accepted data or null to skip check
     *
     * @return void
     */
    private function verifyOobData($isOutOfBand, $data)
    {
        if (!$isOutOfBand) {
            return;
        }

        if ($this->maxOobPacketLength === 0) {
            throw UnsupportedOperationException::oobDataUnsupported($this->socket);
        }

        if ($data !== null && strlen($data) > $this->maxOobPacketLength) {
            throw UnsupportedOperationException::oobDataPackageSizeExceeded(
                $this->socket,
                $this->maxOobPacketLength,
                strlen($data)
            );
        }
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
     * @param Context              $context Socket context
     * @param bool                 $isOutOfBand Flag if it is out-of-band data
     *
     * @return bool Flag whether it is the end of the frame
     */
    private function handleUnreadData(FramePickerInterface $picker, Context $context, $isOutOfBand)
    {
        $unhandledData = $context->getUnreadData($isOutOfBand);
        if ($unhandledData) {
            $context->setUnreadData(
                $picker->pickUpData($unhandledData, $this->getRemoteAddress()),
                $isOutOfBand
            );
        }

        return $picker->isEof();
    }
}
