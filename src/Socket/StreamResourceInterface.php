<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

/**
 * Interface StreamResourceInterface
 */
interface StreamResourceInterface
{
    /**
     * Return socket resource
     *
     * @return resource
     */
    public function getStreamResource();
}
