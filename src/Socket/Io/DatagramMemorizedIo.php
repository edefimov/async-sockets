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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\SocketInterface;

/**
 * Class DatagramMemorizedIo
 */
class DatagramMemorizedIo extends DatagramClientIo
{
    /**
     * Data for this socket
     *
     * @var string
     */
    private $data;

    /**
     * Constructor
     *
     * @param SocketInterface $socket Socket object
     * @param string          $remoteAddress Destination address in form scheme://host:port
     * @param string          $data Datagram for this socket
     */
    public function __construct(SocketInterface $socket, $remoteAddress, $data)
    {
        parent::__construct($socket, $remoteAddress);
        $this->data = (string) $data;
    }

    /** {@inheritdoc} */
    protected function readRawData()
    {
        $result     = $this->data;
        $this->data = '';
        return $result;
    }

    /** {@inheritdoc} */
    protected function isFullFrameRead($socket, FramePickerInterface $picker)
    {
        return $this->data === '' && parent::isFullFrameRead($socket, $picker);
    }
}
