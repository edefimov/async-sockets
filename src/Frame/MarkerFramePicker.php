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
     * bool
     *
     * @var bool
     */
    private $isCaseSensitive;

    /**
     * MarkerFramePicker constructor.
     *
     * @param null|string $startMarker Start marker
     * @param string      $endMarker End marker
     * @param bool        $isCaseSensitive True, if case is important
     */
    public function __construct($startMarker, $endMarker, $isCaseSensitive = true)
    {
        parent::__construct();
        $this->startMarker     = $startMarker;
        $this->endMarker       = $endMarker;
        $this->isCaseSensitive = $isCaseSensitive;
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

        $pos = $this->findMarker($buffer, $this->startMarker);
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

        $pos = $this->findMarker($buffer, $this->endMarker, $this->startPos + strlen($this->startMarker));
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

    /**
     * Performs strpos or stripos according to case sensibility
     *
     * @param string $haystack Where find text
     * @param string $needle What to find
     * @param int    $offset Start offset in $haystack
     *
     * @return bool|int
     */
    protected function findMarker($haystack, $needle, $offset = 0)
    {
        return $this->isCaseSensitive ?
            strpos($haystack, $needle, $offset) :
            stripos($haystack, $needle, $offset);
    }
}
