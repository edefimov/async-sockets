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

use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\NullFrame;

/**
 * Class AbstractSocket
 */
abstract class AbstractSocket implements SocketInterface
{
    /**
     * Socket buffer size
     */
    const SOCKET_BUFFER_SIZE = 8192;

    /**
     * Amount of attempts to set data
     */
    const SEND_ATTEMPTS = 10;

    /**
     * Delay for select operation, microseconds
     */
    const SELECT_DELAY = 25000;

    /**
     * Disconnected state
     */
    const STATE_DISCONNECTED = 0;

    /**
     * Connected state
     */
    const STATE_CONNECTED = 1;

    /**
     * This socket resource
     *
     * @var resource
     */
    private $resource;

    /**
     * Socket state
     *
     * @var int
     */
    private $state;

    /**
     * Flag whether this socket is blocking
     *
     * @var bool
     */
    private $isBlocking = true;

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Create certain socket resource
     *
     * @param string   $address Network address to open in form transport://path:port
     * @param resource $context Valid stream context created by function stream_context_create or null
     *
     * @return resource
     */
    abstract protected function createSocketResource($address, $context);

    /**
     * Check whether given socket resource is connected
     *
     * @param resource $socket Socket resource to check
     *
     * @return bool
     */
    abstract protected function isConnected($socket);

    /**
     * Perform reading data from socket
     *
     * @param resource            $socket Socket resource
     * @param FrameInterface      $frame Frame object to read
     * @param ChunkSocketResponse $previousResponse Previous response from this socket
     *
     * @return SocketResponseInterface
     * @throws NetworkSocketException
     */
    abstract protected function doReadData(
        $socket,
        FrameInterface $frame,
        ChunkSocketResponse $previousResponse = null
    );

    /** {@inheritdoc} */
    public function open($address, $context = null)
    {
        $this->close();

        $this->resource = $this->createSocketResource($address, $context ?: stream_context_get_default());

        $result = false;
        if (is_resource($this->resource)) {
            $result = true;
            $meta   = stream_get_meta_data($this->resource);
            if (isset($meta[ 'blocked' ]) && $meta[ 'blocked' ] != $this->isBlocking) {
                $this->setBlocking($this->isBlocking);
            }
        }

        return $result;
    }

    /** {@inheritdoc} */
    public function close()
    {
        $this->state = self::STATE_DISCONNECTED;
        if ($this->resource) {
            stream_socket_shutdown($this->resource, STREAM_SHUT_RDWR);
            fclose($this->resource);
            $this->resource = null;
        }
    }

    /** {@inheritdoc} */
    public function read(FrameInterface $frame = null, ChunkSocketResponse $previousResponse = null)
    {
        $this->setConnectedState();
        $frame = $previousResponse ? $previousResponse->getFrame() : $frame;
        $frame = $frame ?: new NullFrame();

        return $this->doReadData($this->resource, $frame, $previousResponse);
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
            $write    = [ $this->resource ];
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

    /** {@inheritdoc} */
    public function setBlocking($isBlocking)
    {
        if (is_resource($this->resource)) {
            $result = stream_set_blocking($this->resource, $isBlocking ? 1 : 0);
            $this->throwNetworkSocketExceptionIf(
                $result === false,
                'Failed to switch ' . ($isBlocking ? '' : 'non-') . 'blocking mode.'
            );
        } else {
            $result = true;
        }

        $this->isBlocking = (bool) $isBlocking;

        return $result;
    }

    /** {@inheritdoc} */
    public function getStreamResource()
    {
        return $this->resource;
    }

    /**
     * Throw network operation exception
     *
     * @param bool   $condition Condition, which must evaluates to true for throwing exception
     * @param string $message Exception message
     *
     * @return void
     * @throws NetworkSocketException
     */
    protected function throwNetworkSocketExceptionIf($condition, $message)
    {
        if ($condition) {
            throw new NetworkSocketException($this, $message);
        }
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
        $test = stream_socket_sendto($this->resource, '');
        $this->throwNetworkSocketExceptionIf($test !== 0, 'Failed to send data.');

        $written = fwrite($this->resource, $data, strlen($data));
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
     */
    private function setConnectedState()
    {
        if (!is_resource($this->resource)) {
            $message = $this->state === self::STATE_CONNECTED ?
                'Connection was unexpectedly closed.' :
                'Can not start io operation on uninitialized socket.';
            $this->throwNetworkSocketExceptionIf(true, $message);
        }

        if ($this->state !== self::STATE_CONNECTED) {
            $this->throwExceptionIfNotConnected('Connection refused.');

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
            !$this->isConnected($this->resource),
            $message
        );
    }
}
