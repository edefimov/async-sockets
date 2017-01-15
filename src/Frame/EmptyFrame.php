<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Frame;

/**
 * Class EmptyFrame
 */
class EmptyFrame implements FrameInterface
{
    /**
     * Remote address
     *
     * @var string
     */
    private $remoteAddress;

    /**
     * EmptyFrame constructor.
     *
     * @param string $remoteAddress Remote host address
     */
    public function __construct($remoteAddress)
    {
        $this->remoteAddress = $remoteAddress;
    }

    /** {@inheritdoc} */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    /** {@inheritdoc} */
    public function getData()
    {
        return '';
    }

    /** {@inheritdoc} */
    public function __toString()
    {
        return '';
    }
}
