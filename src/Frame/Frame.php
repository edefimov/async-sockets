<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Frame;

/**
 * Class Frame
 */
class Frame implements FrameInterface
{
    /**
     * Data from network for this object
     *
     * @var string
     */
    private $data;

    /**
     * The source address of this frame
     *
     * @var string
     */
    private $remoteAddress;

    /**
     * SocketResponse constructor.
     *
     * @param string $data Data from network for this response
     * @param string $remoteAddress The source address of this frame
     */
    public function __construct($data, $remoteAddress)
    {
        $this->data = (string) $data;
        $this->remoteAddress = $remoteAddress;
    }

    /** {@inheritdoc} */
    public function getData()
    {
        return $this->data;
    }

    /** {@inheritdoc} */
    public function __toString()
    {
        return $this->getData();
    }

    /** {@inheritdoc} */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }
}
