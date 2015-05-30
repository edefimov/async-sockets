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
     * Return true, if end of frame is reached
     *
     * @return bool
     */
    public function isEof();

    /**
     * Process raw network data. Data should be used to determine end of this concrete frame
     *
     * @param string $chunk Part of data, before calling this method
     * @param int    $lenChunk Length if chunk
     * @param string $data Full data, collected from socket till this moment
     *
     * @return int Length of processed data. Unprocessed data will be passed on next call to this function
     */
    public function handleData($chunk, $lenChunk, $data);
}
