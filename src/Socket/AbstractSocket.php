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

use AsyncSockets\Exception\ConnectionException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
use AsyncSockets\Socket\Io\DisconnectedIo;
use AsyncSockets\Socket\Io\IoInterface;

/**
 * Class AbstractSocket
 */
abstract class AbstractSocket implements SocketInterface
{
    /**
     * Tcp socket type
     */
    const SOCKET_TYPE_TCP = 'tcp';

    /**
     * Udp socket type
     */
    const SOCKET_TYPE_UDP = 'udp';

    /**
     * Unix socket type
     */
    const SOCKET_TYPE_UNIX = 'unix';

    /**
     * Unknown type of socket
     */
    const SOCKET_TYPE_UNKNOWN = '';

    /**
     * This socket resource
     *
     * @var resource
     */
    private $resource;

    /**
     * Flag whether this socket is blocking
     *
     * @var bool
     */
    private $isBlocking = true;

    /**
     * I/O interface
     *
     * @var IoInterface
     */
    private $ioInterface;

    /**
     * AbstractSocket constructor.
     */
    public function __construct()
    {
        $this->setDisconnectedState();
    }

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
     * Create I/O interface for socket
     *
     * @param string $type Type of this socket, one of SOCKET_TYPE_* consts
     *
     * @return IoInterface
     */
    abstract protected function createIoInterface($type);

    /** {@inheritdoc} */
    public function open($address, $context = null)
    {
        $this->close();

        $this->resource = $this->createSocketResource($address, $context ?: stream_context_get_default());

        $result = false;
        if (is_resource($this->resource)) {
            $result = true;
            $meta   = stream_get_meta_data($this->resource);

            /** @noinspection TypeUnsafeComparisonInspection */
            if (isset($meta['blocked']) && $meta['blocked'] != $this->isBlocking) {
                $this->setBlocking($this->isBlocking);
            }

            $this->ioInterface = $this->createIoInterface(
                $this->resolveSocketType()
            );
        }

        return $result;
    }

    /** {@inheritdoc} */
    public function close()
    {
        $this->setDisconnectedState();
        if ($this->resource) {
            stream_socket_shutdown($this->resource, STREAM_SHUT_RDWR);
            fclose($this->resource);
            $this->resource = null;
        }
    }

    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker = null)
    {
        $picker = $picker ?: new NullFramePicker();
        try {
            return $this->ioInterface->read($picker);
        } catch (ConnectionException $e) {
            $this->setDisconnectedState();
            throw $e;
        }
    }

    /** {@inheritdoc} */
    public function write($data)
    {
        try {
            return $this->ioInterface->write($data);
        } catch (ConnectionException $e) {
            $this->setDisconnectedState();
            throw $e;
        }
    }

    /** {@inheritdoc} */
    public function setBlocking($isBlocking)
    {
        if (is_resource($this->resource)) {
            $result = stream_set_blocking($this->resource, $isBlocking ? 1 : 0);
            if ($result === false) {
                throw new NetworkSocketException(
                    $this,
                    'Failed to switch ' . ($isBlocking ? '' : 'non-') . 'blocking mode.'
                );
            }
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
     * Get current socket type
     *
     * @return string One of SOCKET_TYPE_* consts
     */
    private function resolveSocketType()
    {
        $info = stream_get_meta_data($this->resource);
        if (!isset($info['stream_type'])) {
            return self::SOCKET_TYPE_UNKNOWN;
        }

        $parts = explode('/', $info['stream_type']);
        $map   = [
            'tcp'  => self::SOCKET_TYPE_TCP,
            'udp'  => self::SOCKET_TYPE_UDP,
            'unix' => self::SOCKET_TYPE_UNIX,
        ];
        foreach ($parts as $part) {
            if (preg_match('#^(tcp|udp|unix)_socket$#', $part, $pockets)) {
                return $map[$pockets[1]];
            }
        }

        return self::SOCKET_TYPE_UNKNOWN;
    }

    /**
     * Set disconnected state for socket
     *
     * @return void
     */
    private function setDisconnectedState()
    {
        $this->ioInterface = new DisconnectedIo($this);
    }
}
