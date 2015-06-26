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
use AsyncSockets\Socket\Io\TcpServerIo;
use AsyncSockets\Socket\Io\UdpServerIo;

/**
 * Class ServerSocket
 */
class ServerSocket extends AbstractSocket
{
    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        $type = parse_url($address, PHP_URL_SCHEME);
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
            case self::SOCKET_TYPE_TCP:
                return new TcpServerIo($this);
            case self::SOCKET_TYPE_UDP:
                return new UdpServerIo($this);
            default:
                throw new \LogicException("Unsupported socket resource type {$type}");
        }
    }
}
