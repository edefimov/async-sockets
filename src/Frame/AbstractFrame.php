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
 * Class AbstractFrame
 */
abstract class AbstractFrame implements FrameInterface
{
    /**
     * Flag whether this frame is started
     *
     * @var bool
     */
    private $isStarted;

    /**
     * Flag, whether frame is finished
     *
     * @var bool
     */
    private $isFinished;

    /**
     * AbstractFrame constructor.
     */
    public function __construct()
    {
        $this->isStarted  = false;
        $this->isFinished = false;
    }

    /** {@inheritdoc} */
    public function isStarted()
    {
        return $this->isStarted;
    }

    /** {@inheritdoc} */
    public function isEof()
    {
        return $this->isStarted && $this->isFinished;
    }

    /** {@inheritdoc} */
    public function findStartOfFrame($chunk, $lenChunk, $data)
    {
        if ($this->isStarted) {
            return 0;
        }

        $result = $this->doFindStartOfFrame($chunk, $lenChunk, $data);

        if ($result !== null) {
            $this->isStarted = true;
        }

        return $result;
    }

    /** {@inheritdoc} */
    public function handleData($chunk, $lenChunk, $data)
    {
        if (!$this->isStarted || $this->isFinished) {
            return 0;
        }

        return $this->doHandleData($chunk, $lenChunk, $data);
    }

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
    abstract protected function doFindStartOfFrame($chunk, $lenChunk, $data);

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
    abstract protected function doHandleData($chunk, $lenChunk, $data);

    /**
     * Sets finished flag
     *
     * @param boolean $isFinished Flag whether frame is finished
     *
     * @return void
     */
    protected function setFinished($isFinished)
    {
        $this->isFinished = $isFinished;
    }
}
