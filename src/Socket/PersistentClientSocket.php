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
 * Class PersistentClientSocket
 */
class PersistentClientSocket extends AbstractClientSocket
{
    /**
     * Key in php persistent storage to allow multiple persistent connections to the same host
     *
     * @var null|string
     */
    private $persistentKey;

    /**
     * PersistentClientSocket constructor.
     *
     * @param string|null $persistentKey Key in php persistent storage to allow multiple persistent
     *                                   connections to the same host [a-zA-Z0-9_-]
     */
    public function __construct($persistentKey = null)
    {
        parent::__construct();
        $this->persistentKey = $persistentKey;
    }

    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        $resource = stream_socket_client(
            $address . ($this->persistentKey ? '/' . $this->persistentKey : ''),
            $errno,
            $errstr,
            null,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT,
            $context
        );

        if ($errno || $resource === false) {
            throw new NetworkSocketException($this, $errstr, $errno);
        }

        return $resource;
    }
}
