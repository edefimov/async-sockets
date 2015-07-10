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
use AsyncSockets\Socket\Io\StreamedServerIo;
use AsyncSockets\Socket\Io\DatagramServerIo;

/**
 * Class ServerSocket
 */
class ServerSocket extends AbstractSocket
{
    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        $type     = $this->getSocketScheme($address);
        $resource = stream_socket_server(
            $address,
            $errno,
            $errstr,
            $this->getServerFlagsByType($type),
            $context
        );

        if ($errno || $resource === false) {
            throw new NetworkSocketException($this, $errstr, $errno);
        }

        return $resource;
    }

    /**
     * Return socket scheme
     *
     * @param string $address Address in form scheme://host:port
     *
     * @return string|null
     */
    private function getSocketScheme($address)
    {
        $pos = strpos($address, '://');
        if ($pos === false) {
            return null;
        }

        return substr($address, 0, $pos);
    }

    /**
     * Return flags for connection
     *
     * @param string $scheme Socket type being created
     *
     * @return int
     */
    private function getServerFlagsByType($scheme)
    {
        $connectionLessMap = [
            'udp' => 1,
            'udg' => 1,
        ];

        return isset($connectionLessMap[ $scheme ]) ?
            STREAM_SERVER_BIND :
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
    }

    /** {@inheritdoc} */
    protected function createIoInterface($type, $address)
    {
        switch ($type) {
            case self::SOCKET_TYPE_UNIX:
                return new StreamedServerIo($this);
            case self::SOCKET_TYPE_TCP:
                return new StreamedServerIo($this);
            case self::SOCKET_TYPE_UDG:
                return new DatagramServerIo($this, true);
            case self::SOCKET_TYPE_UDP:
                return new DatagramServerIo($this, false);
            default:
                throw new \LogicException("Unsupported socket resource type {$type}");
        }
    }
}
