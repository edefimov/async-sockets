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
 * Class MarkerFramePicker
 */
class MarkerFramePicker extends AbstractFramePicker
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
    private $startPos;

    /**
     * MarkerFramePicker constructor.
     *
     * @param null|string $startMarker Start marker
     * @param string      $endMarker End marker
     */
    public function __construct($startMarker, $endMarker)
    {
        parent::__construct();
        $this->startMarker = $startMarker;
        $this->endMarker   = $endMarker;
    }

    /**
     * Find start of data in frame
     *
     * @param string $buffer Collected data for frame
     *
     * @return bool True if start of frame is found
     */
    protected function resolveStartOfFrame($buffer)
    {
        if ($this->startPos !== null) {
            return true;
        }

        if ($this->startMarker === null) {
            $this->startPos = 0;
            return true;
        }

        $pos = strpos($buffer, $this->startMarker);
        if ($pos !== false) {
            $this->startPos = $pos;
            return true;
        }

        return false;
    }

    /** {@inheritdoc} */
    protected function doHandleData($chunk, &$buffer)
    {
        $buffer .= $chunk;
        if (!$this->resolveStartOfFrame($buffer)) {
            return '';
        }

        $pos = strpos($buffer, $this->endMarker, $this->startPos + strlen($this->startMarker));
        if ($pos === false) {
            return '';
        }

        $this->setFinished(true);
        $result = substr($buffer, $pos + strlen($this->endMarker));
        $buffer = substr($buffer, $this->startPos, $pos + strlen($this->endMarker) - $this->startPos);
        return $result !== false ? $result : '';
    }

    /** {@inheritdoc} */
    protected function doCreateFrame($buffer)
    {
        if ($this->isEof()) {
            return new Frame($buffer);
        }

        $data = $this->startPos === null ? '' : substr($buffer, $this->startPos);
        return new Frame($data);
    }
}
