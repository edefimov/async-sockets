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

use AsyncSockets\Exception\NetworkSocketException;

/**
 * Class AcceptedSocket
 */
class AcceptedSocket extends AbstractClientSocket
{
    /**
     * Accepted client
     *
     * @var resource
     */
    private $acceptedResource;

    /**
     * AcceptedSocket constructor.
     *
     * @param resource $acceptedResource Accepted client socket
     */
    public function __construct($acceptedResource)
    {
        parent::__construct();
        $this->acceptedResource = $acceptedResource;
    }

    /** {@inheritdoc} */
    protected function createSocketResource($address, $context)
    {
        if ($this->acceptedResource) {
            $result                 = $this->acceptedResource;
            $this->acceptedResource = null;

            return $result;
        }

        throw new NetworkSocketException($this, 'Remote client socket can not be reopened.');
    }
}
