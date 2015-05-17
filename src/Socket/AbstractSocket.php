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

/**
 * Class AbstractSocket
 */
abstract class AbstractSocket implements SocketInterface
{
    /**
     * Socket reading buffer size
     */
    const SOCKET_BUFFER_SIZE = 8192;

    /**
     * This socket resource
     *
     * @var resource
     */
    private $resource;

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

    /** {@inheritdoc} */
    public function open($address, $context = null)
    {
        $this->close();

        $this->resource = $this->createSocketResource($address, $context ?: stream_context_get_default());

        return is_resource($this->resource);
    }

    /** {@inheritdoc} */
    public function close()
    {
        if ($this->resource) {
            stream_socket_shutdown($this->resource, STREAM_SHUT_RDWR);
            fclose($this->resource);
            $this->resource = null;
        }
    }

    /** {@inheritdoc} */
    public function read(PartialSocketResponse $previousResponse = null)
    {
        $result       = $previousResponse ? $previousResponse->getData() : $this->readActualData();
        $microseconds = 200000;
        do {
            $read     = [ $this->resource ];
            $nomatter = null;
            $select = stream_select($read, $nomatter, $nomatter, 0, $microseconds);
            if ($select === false) {
                $this->throwNetworkSocketException('Failed to read data');
            }

            if ($select === 0) {
                break;
            }

            // work-around https://bugs.php.net/bug.php?id=52602
            $rawData = stream_socket_recvfrom($this->resource, self::SOCKET_BUFFER_SIZE, MSG_PEEK);

            if ($rawData !== false) {
                if ($rawData === '') {
                    break;
                }

                $data = $this->readActualData();

                $result .= $data;
            } else {
                return new PartialSocketResponse($result);
            }
        } while (true);

        return new SocketResponse($result);
    }

    /** {@inheritdoc} */
    public function write($data)
    {
        $result     = 0;
        $dataLength = strlen($data);
        do {
            $written = fwrite($this->resource, $data, strlen($data));
            if ($written === false) {
                $this->throwNetworkSocketException('Socket write failed');
            }

            $data    = substr($data, $written);
            $result += $written;
        } while ($result < $dataLength && $data !== false);

        return $result;
    }

    /** {@inheritdoc} */
    public function setBlocking($isBlocking)
    {
        $result = stream_set_blocking($this->resource, $isBlocking ? 1 : 0);
        if ($result === false) {
            $this->throwNetworkSocketException(
                'Failed to switch ' . ($isBlocking ? '': 'non-') . 'blocking mode'
            );
        }

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
     * @param string $message Exception message
     *
     * @return void
     * @throws NetworkSocketException
     */
    protected function throwNetworkSocketException($message)
    {
        throw new NetworkSocketException($this, $message);
    }

    /**
     * Read actual data from socket
     *
     * @return string
     */
    private function readActualData()
    {
        $data = fread($this->resource, self::SOCKET_BUFFER_SIZE);
        if ($data === false) {
            $this->throwNetworkSocketException('Failed to read data');
        }

        return $data;
    }
}
