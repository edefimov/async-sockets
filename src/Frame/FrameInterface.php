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
     * Determines start of this frame
     *
     * @param string $chunk Part of data, before calling this method
     * @param int    $lenChunk Length if chunk
     * @param string $data Data, collected from socket till this moment, excluding $chunk
     *
     * @return int|null Offset in $chunk where this frame starts.
     *                  Can be negative if beginning of this frame was before current chunk.
     *                  If null, then there is no start frame in given chunk
     */
    public function findStartOfFrame($chunk, $lenChunk, $data);

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
     * @param string $data Data, collected from socket till this moment, excluding $chunk, and beginning from
     *                      start of this frame
     *
     * @return int Length of processed data. Unprocessed data will be passed on next call to this function.
     *             If negative value is returned, then frame data will be truncated to returned length
     */
    public function handleData($chunk, $lenChunk, $data);
}
