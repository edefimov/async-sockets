<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Exception\BadResourceException;
use AsyncSockets\Exception\ConnectionException;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\Io\Context;
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
     * Unix datagram socket type
     */
    const SOCKET_TYPE_UDG = 'udg';

    /**
     * Unknown type of socket
     */
    const SOCKET_TYPE_UNKNOWN = '';

    /**
     * Array of socket resources
     *
     * @var resource[]
     */
    private static $resources;

    /**
     * Flag if we have shutdown function
     *
     * @var bool
     */
    private static $hasShutdownClearer = false;

    /**
     * Gracefully disconnects sockets when application terminating
     *
     * @return void
     */
    public static function shutdownSocketCleaner()
    {
        foreach (self::$resources as $resource) {
            try {
                $socket = self::fromResource($resource);
                if (!($socket instanceof PersistentClientSocket)) {
                    $socket->close();
                }
            } catch (BadResourceException $e) {
                // nothing required
            }
        }
    }

    /**
     * Creates socket object from given resource
     *
     * @param resource $resource Opened socket resource
     *
     * @return SocketInterface
     */
    public static function fromResource($resource)
    {
        $map = [
            'stream'            => 0,
            'persistent stream' => 1,
        ];

        $type = get_resource_type($resource);
        if (!isset($map[$type])) {
            throw BadResourceException::notSocketResource($type);
        }

        $isServer     = 0;
        $isPersistent = $map[$type];
        $localAddress = stream_socket_get_name($resource, false);
        if ($localAddress === false) {
            throw BadResourceException::canNotObtainLocalAddress();
        }

        try {
            if (($s = stream_socket_client(
                    $localAddress,
                    $errno,
                    $errstr,
                    (int) ini_get('default_socket_timeout'),
                    STREAM_CLIENT_CONNECT,
                    stream_context_get_default()
                )) !== false
            ) {
                $isServer = 1;
                fclose($s);
            }
        } catch (\Exception $e) {
            // do nothing
        } catch (\Throwable $e) {
            // do nothing
        }

        // [isServer][isPersistent] => class
        $objectMap = [
            0 => [
                0 => 'AsyncSockets\Socket\ClientSocket',
                1 => 'AsyncSockets\Socket\PersistentClientSocket',
            ],
            1 => [
                0 => 'AsyncSockets\Socket\ServerSocket',
                1 => 'AsyncSockets\Socket\ServerSocket',
            ]
        ];

        /** @var AbstractSocket $result */
        $result = new $objectMap[$isServer][$isPersistent]();
        $result->setupSocketResource($resource, (string) stream_socket_get_name($resource, true));

        return $result;
    }

    /**
     * This socket resource
     *
     * @var resource
     */
    private $resource;

    /**
     * I/O interface
     *
     * @var IoInterface
     */
    private $ioInterface;

    /**
     * Socket address
     *
     * @var string
     */
    private $remoteAddress;

    /**
     * Context for this socket
     *
     * @var Context
     */
    private $context;

    /**
     * AbstractSocket constructor.
     */
    public function __construct()
    {
        $this->setDisconnectedState();
        $this->context = new Context();

        if (!self::$hasShutdownClearer) {
            self::$hasShutdownClearer = true;
            register_shutdown_function(['AsyncSockets\Socket\AbstractSocket', 'shutdownSocketCleaner']);
        }
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

    /** {@inheritdoc} */
    public function open($address, $context = null)
    {
        $resource = $this->createSocketResource(
            $address,
            $context ?: stream_context_get_default()
        );

        $this->setupSocketResource($resource, $address);
    }

    /** {@inheritdoc} */
    public function close()
    {
        if ($this->resource) {
            unset(self::$resources[(string) $this->resource]);
            $this->setDisconnectedState();
            stream_socket_shutdown($this->resource, STREAM_SHUT_RDWR);
            fclose($this->resource);
            $this->resource      = null;
            $this->remoteAddress = null;
        }
    }

    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker, $isOutOfBand = false)
    {
        try {
            return $this->ioInterface->read($picker, $this->context, $isOutOfBand);
        } catch (ConnectionException $e) {
            $this->setDisconnectedState();
            throw $e;
        }
    }

    /** {@inheritdoc} */
    public function write($data, $isOutOfBand = false)
    {
        try {
            return $this->ioInterface->write($data, $this->context, $isOutOfBand);
        } catch (ConnectionException $e) {
            $this->setDisconnectedState();
            throw $e;
        }
    }

    /** {@inheritdoc} */
    public function getStreamResource()
    {
        return $this->resource;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->remoteAddress ?
            sprintf(
                '[#%s, %s]',
                preg_replace('/Resource id #(\d+)/i', '$1', (string) $this->resource),
                $this->remoteAddress
            ) :
            '[closed socket]';
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
     * @param string $address Address passed to open method
     *
     * @return IoInterface
     */
    abstract protected function createIoInterface($type, $address);

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
            'udg'  => self::SOCKET_TYPE_UDG,
            'unix' => self::SOCKET_TYPE_UNIX,
        ];

        $regexp = '#^('. implode('|', array_keys($map)) . ')_socket$#';
        foreach ($parts as $part) {
            if (preg_match($regexp, $part, $pockets)) {
                return $map[$pockets[1]];
            }
        }

        return self::SOCKET_TYPE_UNKNOWN;
    }

    /**
     * Set up internal data for given resource
     *
     * @param resource $resource Socket resource
     * @param string   $address Socket address
     *
     * @return void
     */
    private function setupSocketResource($resource, $address)
    {
        if (!is_resource($resource)) {
            throw new ConnectionException(
                $this,
                'Can not allocate socket resource.'
            );
        }

        $this->resource      = $resource;
        $this->remoteAddress = $address;

        // https://bugs.php.net/bug.php?id=51056
        stream_set_blocking($this->resource, 0);

        // https://bugs.php.net/bug.php?id=52602
        stream_set_timeout($this->resource, 0, 0);
        stream_set_chunk_size($this->resource, IoInterface::SOCKET_BUFFER_SIZE);

        $this->ioInterface = $this->createIoInterface(
            $this->resolveSocketType(),
            $address
        );

        self::$resources[(string) $resource] = $resource;
        $this->context->reset();
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        return $this->ioInterface->isConnected();
    }
}
