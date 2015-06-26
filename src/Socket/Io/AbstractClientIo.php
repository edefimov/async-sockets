<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Exception\ConnectionException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
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
     * Read raw data from network
     *
     * @return string|bool Read data or false on error
     */
    abstract protected function readRawData();

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

    /** {@inheritdoc} */
    final public function read(FramePickerInterface $picker)
    {
        $this->setConnectedState();
        $unhandledData = $picker->pickUpData($this->unhandledData);

        $isEndOfFrame        = false;
        $resource            = $this->socket->getStreamResource();
        $this->unhandledData = $unhandledData;
        do {
            if ($this->isFullFrameRead($resource, $picker)) {
                $isEndOfFrame = true;
                break;
            }

            $data = $this->readRawData();
            if ($data === false || $data === '') {
                $isEndOfFrame = ($data === '' && $picker instanceof NullFramePicker) || $isEndOfFrame;
                break;
            }

            $this->unhandledData = $picker->pickUpData($this->unhandledData . $data);
        } while (!$isEndOfFrame);

        $frame = $picker->createFrame();
        if (!$isEndOfFrame) {
            $frame = new PartialFrame($frame);
        }

        return $frame;
    }

    /** {@inheritdoc} */
    final public function write($data)
    {
        $this->setConnectedState();

        $result       = 0;
        $dataLength   = strlen($data);
        $microseconds = self::SELECT_DELAY;
        $attempts     = self::SEND_ATTEMPTS;

        do {
            $write    = [ $this->socket->getStreamResource() ];
            $nomatter = null;
            $select   = stream_select($nomatter, $write, $nomatter, 0, $microseconds);
            $this->throwNetworkSocketExceptionIf($select === false, 'Failed to send data.');

            $bytesWritten = $write ? $this->writeRawData($data) : 0;
            $attempts     = $bytesWritten === 0 ? $attempts - 1 : self::SEND_ATTEMPTS;

            $this->throwNetworkSocketExceptionIf(
                !$attempts && $result !== $dataLength,
                'Failed to send data.'
            );

            $data = substr($data, $bytesWritten);
            $result += $bytesWritten;
        } while ($result < $dataLength && $data !== false);

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
}
