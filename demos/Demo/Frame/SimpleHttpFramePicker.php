<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\Frame;

use AsyncSockets\Frame\AbstractFramePicker;
use AsyncSockets\Frame\FixedLengthFramePicker;
use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Frame\MarkerFramePicker;

/**
 * Class SimpleHttpFrame
 */
class SimpleHttpFramePicker extends AbstractFramePicker
{
    /**
     * Header frame picker
     *
     * @var FramePickerInterface
     */
    private $headerFrame;

    /**
     * Content frame picker
     *
     * @var FramePickerInterface
     */
    private $contentPicker;

    /**
     * SimpleHttpFrame constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->headerFrame = new MarkerFramePicker('HTTP', "\r\n\r\n", true);
    }

    /** {@inheritDoc} */
    protected function doHandleData($chunk, $remoteAddress, &$buffer)
    {
        $result = $this->headerFrame->pickUpData($chunk, $remoteAddress);
        if ($result) {
            if (!$this->contentPicker) {
                $this->contentPicker = $this->createContentFramePicker((string) $this->headerFrame->createFrame());
            }

            $result = $this->contentPicker->pickUpData($result, $remoteAddress);
            if ($this->contentPicker->isEof()) {
                $buffer = (string) $this->headerFrame->createFrame() .
                          (string) $this->contentPicker->createFrame();
                $this->setFinished(true);

                return $result;
            }
        }

        return '';
    }

    /** {@inheritDoc} */
    protected function doCreateFrame($buffer, $remoteAddress)
    {
        return new Frame($buffer, $remoteAddress);
    }

    /**
     * Return implementation of FramePickerInterface to read content body
     *
     * @param string $headers Headers
     *
     * @return FramePickerInterface
     * @throws \InvalidArgumentException
     */
    private function createContentFramePicker($headers)
    {
        foreach (explode("\r\n", $headers) as $header) {
            if (strpos($header, 'Content-Length: ') === 0) {
                list(, $result) = explode(':', $header);
                return new FixedLengthFramePicker((int) trim($result));
            }

            if (strpos($header, 'Transfer-Encoding: ') === 0) {
                return new HttpChunkTransferEncodingPicker();
            }
        }

        throw new \InvalidArgumentException('Can not resolve transfer type: ' . $headers);
    }
}
