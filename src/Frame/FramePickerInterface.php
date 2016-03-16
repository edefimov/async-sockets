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
 * Interface FramePickerInterface
 */
interface FramePickerInterface
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
     * @param string $chunk Chunk read from socket
     * @param string $remoteAddress Remote client address sent this chunk
     *
     * @return string Unprocessed data after end of frame
     */
    public function pickUpData($chunk, $remoteAddress);

    /**
     * Create frame from picked data
     *
     * @return FrameInterface
     */
    public function createFrame();
}
