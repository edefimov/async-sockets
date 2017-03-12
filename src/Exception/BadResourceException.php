<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Exception;

/**
 * Class BadResourceException
 */
class BadResourceException extends \InvalidArgumentException
{
    /**
     * Create error for incorrect resource
     *
     * @param string $type Resource type
     *
     * @return BadResourceException
     */
    public static function notSocketResource($type)
    {
        return new self(
            sprintf('Can not create socket from resource "%s"', $type)
        );
    }

    /**
     * Can not get local socket address
     *
     * @return BadResourceException
     */
    public static function canNotObtainLocalAddress()
    {
        return new self('Can not retrieve local socket address.');
    }
}
