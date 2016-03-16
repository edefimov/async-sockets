<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Frame;

/**
 * Class PartialFrame. Special object indicates that data inside frame is incomplete
 */
class PartialFrame implements FrameInterface
{
    /**
     * Original frame
     *
     * @var FrameInterface
     */
    private $original;

    /**
     * PartialFrame constructor.
     *
     * @param FrameInterface $original Original frame
     */
    public function __construct(FrameInterface $original)
    {
        $this->original = $original;
    }

    /** {@inheritdoc} */
    public function getData()
    {
        return $this->original->getData();
    }

    /** {@inheritdoc} */
    public function __toString()
    {
        return (string) $this->original;
    }

    /** {@inheritdoc} */
    public function getRemoteAddress()
    {
        return $this->original->getRemoteAddress();
    }
}
