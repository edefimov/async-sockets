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

/**
 * Class AbstractSocketResponse
 */
abstract class AbstractSocketResponse implements SocketResponseInterface
{
    /**
     * Data from network for this object
     *
     * @var string
     */
    protected $data;

    /**
     * Frame this data is created from
     *
     * @var FramePickerInterface
     */
    protected $framePicker;

    /**
     * SocketResponse constructor.
     *
     * @param FramePickerInterface $frame Frame this data was created from
     * @param string         $data Data from network
     */
    public function __construct(FramePickerInterface $frame, $data)
    {
        $this->data        = (string) $data;
        $this->framePicker = $frame;
    }

    /** {@inheritdoc} */
    public function __toString()
    {
        return $this->getData();
    }

    /** {@inheritdoc} */
    public function getFramePicker()
    {
        return $this->framePicker;
    }
}
