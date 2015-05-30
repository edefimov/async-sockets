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
 * Class NullFrame. Special type of frame, indicates that data should be read
 * until all data is not received
 */
final class NullFrame implements FrameInterface
{
    /** {@inheritdoc} */
    public function isEof()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function handleData($chunk, $lenChunk, $data)
    {
        return $lenChunk;
    }
}
