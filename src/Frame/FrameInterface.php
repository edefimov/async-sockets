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
 * Interface FrameInterface
 */
interface FrameInterface
{
    /**
     * Return remote address these data received from
     *
     * @return string
     */
    public function getRemoteAddress();

    /**
     * Return content of this frame
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
