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
 * Class ChunkSocketResponse
 */
class ChunkSocketResponse extends AbstractSocketResponse
{
    /**
     * Previous chunk of this response
     *
     * @var ChunkSocketResponse
     */
    private $previousChunk;

    /**
     * Constructor
     *
     * @param FramePickerInterface      $frame Frame this data was created from
     * @param string              $data Data for this chunk
     * @param ChunkSocketResponse $previousChunk Previous chunk
     */
    public function __construct(FramePickerInterface $frame, $data, ChunkSocketResponse $previousChunk = null)
    {
        parent::__construct($frame, $data);
        $this->previousChunk = $previousChunk;
    }

    /** {@inheritdoc} */
    public function getData()
    {
        /** @var ChunkSocketResponse[] $chunks */
        $chunks       = [];
        $currentChunk = $this;
        do {
            $chunks[]     = $currentChunk;
            $currentChunk = $currentChunk->getPreviousChunk();
        } while ($currentChunk);

        $result = '';
        for ($i = count($chunks)-1; $i >= 0; --$i) {
            $result .= $chunks[$i]->data;
        }

        return $result;
    }

    /**
     * Return PreviousChunk
     *
     * @return ChunkSocketResponse
     */
    public function getPreviousChunk()
    {
        return $this->previousChunk;
    }

    /**
     * Return data inside this chunk only
     *
     * @return string
     */
    public function getChunkData()
    {
        return $this->data;
    }
}
