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
 * Interface SocketResponseInterface
 */
interface SocketResponseInterface
{
    /**
     * Return framePicker used to create this response
     *
     * @return FramePickerInterface
     */
    public function getFramePicker();

    /**
     * Return full content of this response
     *
     * @return string
     */
    public function getData();

    /**
     * Standard php __toString method, should return the same result as getData
     *
     * @return string
     * @see getData
     */
    public function __toString();
}
