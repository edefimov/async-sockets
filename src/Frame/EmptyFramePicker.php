<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Frame;

/**
 * Class EmptyFramePicker
 */
class EmptyFramePicker extends AbstractFramePicker
{
    /** {@inheritdoc} */
    protected function doHandleData($chunk, $remoteAddress, &$buffer)
    {
        $this->setFinished(true);
        return $chunk;
    }

    /** {@inheritdoc} */
    protected function doCreateFrame($buffer, $remoteAddress)
    {
        return new EmptyFrame($remoteAddress);
    }
}
