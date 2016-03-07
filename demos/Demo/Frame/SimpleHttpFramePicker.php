<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
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
    protected function doHandleData($chunk, &$buffer)
    {
        $result = $this->headerFrame->pickUpData($chunk);
        if ($result) {
            if (!$this->contentPicker) {
                $this->contentPicker = new FixedLengthFramePicker(
                    $this->getContentLength(
                        (string) $this->headerFrame->createFrame()
                    )
                );

                $this->contentPicker = new HttpChunkTransferEncodingPicker();
            }

            $result = $this->contentPicker->pickUpData($result);
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
    protected function doCreateFrame($buffer)
    {
        return new Frame($buffer);
    }

    /**
     * Return value of content-length header
     *
     * @param string $headers Headers
     *
     * @return int|null
     */
    private function getContentLength($headers)
    {
        foreach (explode("\r\n", $headers) as $header) {
            if (strpos($header, 'Content-Length: ') === 0) {
                list(, $result) = explode(':', $header);
                return (int) trim($result);
            }
        }

        return null;
    }
}
