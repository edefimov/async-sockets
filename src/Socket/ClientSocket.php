<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Exception\FrameSocketException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
use AsyncSockets\Socket\Assistant\FrameProcessor;

/**
 * Class ClientSocket
 */
class ClientSocket extends AbstractSocket
{
    /**
     * Frame processor
     *
     * @var FrameProcessor
     */
    private $frameProcessor;

    /** {@inheritdoc} */
    public function close()
    {
        $this->frameProcessor = null;
        parent::close();
    }

    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        $resource = stream_socket_client(
            $address,
            $errno,
            $errstr,
            null,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
            $context
        );

        if ($errno || $resource === false) {
            throw new NetworkSocketException($this, $errstr, $errno);
        }

        return $resource;
    }

    /** {@inheritdoc} */
    protected function doReadData($socket, FramePickerInterface $frame)
    {
        $result       = '';
        $isEndOfFrame = false;

        do {
            if ($this->isFullFrameRead($socket, $frame)) {
                $isEndOfFrame = true;
                break;
            }

            // work-around https://bugs.php.net/bug.php?id=52602
            $rawData = stream_socket_recvfrom($socket, self::SOCKET_BUFFER_SIZE, MSG_PEEK);
            switch (true) {
                case $rawData === '':
                    $isEndOfFrame = $frame instanceof NullFramePicker || $isEndOfFrame;
                    break 2;
                case $rawData === false:
                    break 2;
                default:
                    $actualData = $this->readActualData($socket);
                    $result     = $this->getFrameProcessor()->processReadFrame($frame, $actualData, $result);

                    $isEndOfFrame = !($frame instanceof NullFramePicker) && $frame->isEof();
            }
        } while (!$isEndOfFrame);

        if ($isEndOfFrame) {
            return new SocketResponse($result);
        } else {
            return new ChunkSocketResponse($result);
        }
    }

    /**
     * Return FrameProcessor object
     *
     * @return FrameProcessor
     */
    private function getFrameProcessor()
    {
        if (!$this->frameProcessor) {
            $this->frameProcessor = new FrameProcessor();
        }

        return $this->frameProcessor;
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
     * @param FramePickerInterface $frame Frame object to check
     *
     * @return bool
     * @throws FrameSocketException If socket data is ended and framePicker eof is not reached
     */
    private function isFullFrameRead($socket, FramePickerInterface $frame)
    {
        $read     = [ $socket ];
        $nomatter = null;
        $select   = stream_select($read, $nomatter, $nomatter, 0, self::SELECT_DELAY);
        $this->throwNetworkSocketExceptionIf($select === false, 'Failed to read data.');

        if ($select === 0) {
            if ($frame->isEof()) {
                return true;
            } else {
                throw new FrameSocketException($frame, $this, 'Failed to receive desired framePicker.');
            }
        }

        return false;
    }

    /** {@inheritdoc} */
    protected function isConnected($socket)
    {
        $name = stream_socket_get_name($socket, true);
        return $name !== false;
    }
}
