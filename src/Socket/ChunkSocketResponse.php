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
 
/**
 * Class ChunkSocketResponse
 */
class ChunkSocketResponse extends SocketResponse
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
     * @param string              $data Data for this chunk
     * @param ChunkSocketResponse $previousChunk Previous chunk
     */
    public function __construct($data, ChunkSocketResponse $previousChunk = null)
    {
        parent::__construct($data);
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
            $currentChunk = $currentChunk->previousChunk;
        } while ($currentChunk);

        $result = '';
        for ($i = count($chunks)-1; $i >= 0; --$i) {
            $result .= $chunks[$i]->getOriginalData();
        }

        return $result;
    }

    /**
     * Return data inside this chunk only
     *
     * @return string
     */
    public function getChunkData()
    {
        return $this->getOriginalData();
    }
}
