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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\NullFramePicker;
use AsyncSockets\Socket\Io\IoInterface;
use AsyncSockets\Socket\Io\DatagramMemorizedIo;

/**
 * Class UdpClientSocket
 */
class UdpClientSocket implements SocketInterface, WithoutConnectionInterface
{
    /**
     * Original server socket
     *
     * @var SocketInterface
     */
    private $origin;

    /**
     * I/O interface
     *
     * @var IoInterface
     */
    private $ioInterface;

    /**
     * UdpClientSocket constructor.
     *
     * @param SocketInterface $origin Original server socket
     * @param string          $remoteAddress Client address
     * @param string          $data Data for this client
     */
    public function __construct(SocketInterface $origin, $remoteAddress, $data)
    {
        $this->origin      = $origin;
        $this->ioInterface = new DatagramMemorizedIo($this, $remoteAddress, $data);
    }

    /** {@inheritdoc} */
    public function open($address, $context = null)
    {
        // empty body
    }

    /** {@inheritdoc} */
    public function close()
    {
        // empty body
    }

    /** {@inheritdoc} */
    public function read(FramePickerInterface $picker = null)
    {
        return $this->ioInterface->read($picker ?: new NullFramePicker());
    }

    /** {@inheritdoc} */
    public function write($data)
    {
        return $this->ioInterface->write($data);
    }

    /** {@inheritdoc} */
    public function getStreamResource()
    {
        return $this->origin->getStreamResource();
    }
}
