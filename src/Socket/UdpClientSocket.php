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

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\Io\Context;
use AsyncSockets\Socket\Io\DatagramMemorizedIo;
use AsyncSockets\Socket\Io\IoInterface;

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
     * Socket context
     *
     * @var Context
     */
    private $context;

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
        $this->context     = new Context();
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
    public function read(FramePickerInterface $picker, $isOutOfBand = false)
    {
        return $this->ioInterface->read($picker, $this->context, $isOutOfBand);
    }

    /** {@inheritdoc} */
    public function write($data, $isOutOfBand = false)
    {
        return $this->ioInterface->write($data, $this->context, $isOutOfBand);
    }

    /** {@inheritdoc} */
    public function getStreamResource()
    {
        return $this->origin->getStreamResource();
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return (string) $this->origin;
    }

    /**
     * @inheritDoc
     */
    public function isServer()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isClient()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        return $this->ioInterface->isConnected();
    }
}
