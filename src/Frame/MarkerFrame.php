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
 * Class MarkerFrame
 */
class MarkerFrame extends AbstractFrame
{
    /**
     * Frame start byte sequence or null
     *
     * @var string|null
     */
    private $startMarker;

    /**
     * Frame end marker
     *
     * @var string
     */
    private $endMarker;

    /**
     * Offset to search for end marker during data handling
     *
     * @var int
     */
    private $offsetForEndMarker;

    /**
     * MarkerFrame constructor.
     *
     * @param null|string $startMarker Start marker
     * @param string      $endMarker End marker
     */
    public function __construct($startMarker, $endMarker)
    {
        parent::__construct();
        $this->startMarker        = $startMarker;
        $this->endMarker          = $endMarker;
        $this->offsetForEndMarker = 0;
    }

    /** {@inheritdoc} */
    protected function doFindStartOfFrame($chunk, $lenChunk, $data)
    {
        if ($this->startMarker === null) {
            return 0;
        }

        $pos = $this->findMarker($this->startMarker, $chunk, $data);
        if ($pos !== null) {
            $this->offsetForEndMarker = $pos + strlen($this->startMarker);
            return $pos;
        }

        return null;
    }

    /**
     * Return StartMarker
     *
     * @return string|null
     */
    public function getStartMarker()
    {
        return $this->startMarker;
    }

    /**
     * Return EndMarker
     *
     * @return string
     */
    public function getEndMarker()
    {
        return $this->endMarker;
    }

    /** {@inheritdoc} */
    protected function doHandleData($chunk, $lenChunk, $data)
    {
        $pos                      = $this->findMarker($this->endMarker, $chunk, $data, $this->offsetForEndMarker);
        $this->offsetForEndMarker = 0;
        if ($pos === null) {
            return $lenChunk;
        }

        $this->setFinished(true);
        return $pos + strlen($this->endMarker);
    }

    /**
     * Search marker in data
     *
     * @param string $marker Marker to search
     * @param string $chunk Read chunk
     * @param string $data All data except chunk
     * @param int    $offset Offset to start marker from
     *
     * @return int|null
     */
    private function findMarker($marker, $chunk, $data, $offset = 0)
    {
        $lenMarker = strlen($marker);
        $lastPart  = (string) substr($data, -$lenMarker, $lenMarker);
        $pos       = strpos($lastPart . $chunk, $marker, $offset);
        if ($pos !== false) {
            return $lastPart ? $pos - $lenMarker : $pos;
        }

        return null;
    }
}
