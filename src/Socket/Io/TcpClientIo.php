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
use AsyncSockets\Exception\FrameSocketException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
use AsyncSockets\Frame\PartialFrame;

/**
 * Class TcpClientIo
 */
class TcpClientIo extends AbstractIo
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
     * Unhandled portion of data at the end of framePicker
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

    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker)
    {
        $this->setConnectedState();

        $isEndOfFrame        = false;
        $resource            = $this->socket->getStreamResource();
        $this->unhandledData = $picker->pickUpData($this->unhandledData);
        do {
            if ($this->isFullFrameRead($resource, $picker)) {
                $isEndOfFrame = true;
                break;
            }

            // work-around https://bugs.php.net/bug.php?id=52602
            $rawData = stream_socket_recvfrom($resource, self::SOCKET_BUFFER_SIZE, MSG_PEEK);
            if ($rawData === false || $rawData === '') {
                $isEndOfFrame = ($rawData === '' && $picker instanceof NullFramePicker) || $isEndOfFrame;
                break;
            }

            $actualData          = $this->readActualData($resource);
            $this->unhandledData = $picker->pickUpData($this->unhandledData . $actualData);
        } while (!$isEndOfFrame);

        $frame = $picker->createFrame();
        if (!$isEndOfFrame) {
            $frame = new PartialFrame($frame);
        }

        return $frame;
    }

    /**
     * Read actual data from socket
     *
     * @param resource $socket Socket resource object
     *
     * @return string
     */
    private function readActualData($socket)
    {
        $data = fread($socket, self::SOCKET_BUFFER_SIZE);
        $this->throwNetworkSocketExceptionIf($data === false, 'Failed to read data.');

        if ($data === '') {
            $this->throwExceptionIfNotConnected('Remote connection has been lost.');
        }

        return $data;
    }

    /**
     * Checks whether all framePicker data is read
     *
     * @param resource       $socket Socket resource object
     * @param FramePickerInterface $picker Frame object to check
     *
     * @return bool
     * @throws FrameSocketException If socket data is ended and framePicker eof is not reached
     */
    private function isFullFrameRead($socket, FramePickerInterface $picker)
    {
        if ($picker->isEof() && !($picker instanceof NullFramePicker)) {
            return true;
        }

        $read     = [ $socket ];
        $nomatter = null;
        $select   = stream_select($read, $nomatter, $nomatter, 0, self::SELECT_DELAY);
        if ($select === false) {
            throw new NetworkSocketException($this->socket, 'Failed to read data.');
        }

        if ($select === 0) {
            if ($picker->isEof()) {
                return true;
            } else {
                throw new FrameSocketException($picker, $this->socket, 'Failed to receive desired picker.');
            }
        }

        return false;
    }

    /** {@inheritdoc} */
    public function write($data)
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

            $bytesWritten = $write ? $this->writeActualData($data) : 0;
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
     * Writes given data to socket
     *
     * @param string $data Data to write
     *
     * @return int Amount of written bytes
     */
    private function writeActualData($data)
    {
        $resource = $this->socket->getStreamResource();
        $test     = stream_socket_sendto($resource, '');
        $this->throwNetworkSocketExceptionIf($test !== 0, 'Failed to send data.');

        $written = fwrite($resource, $data, strlen($data));
        $this->throwNetworkSocketExceptionIf($written === false, 'Failed to send data.');

        if ($written === 0) {
            $this->throwExceptionIfNotConnected('Remote connection has been lost.');
        }

        return $written;
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
     * Check whether given socket resource is connected
     *
     * @return bool
     */
    protected function isConnected()
    {
        $name = stream_socket_get_name($this->socket->getStreamResource(), true);
        return $name !== false;
    }
}
