<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

/**
 * Class Context
 */
class Context
{
    /**
     * Unread data in socket
     *
     * @var string[]
     */
    private $unreadData = ['', ''];

    /**
     * Return UnreadData
     *
     * @param bool $isOutOfBand Flag if it is out of band data
     *
     * @return string
     */
    public function getUnreadData($isOutOfBand = false)
    {
        return $this->unreadData[(int) (bool) $isOutOfBand];
    }

    /**
     * Sets UnreadData
     *
     * @param string $unreadData New value for UnreadData
     * @param bool $isOutOfBand Flag if it is out of band data
     *
     * @return void
     */
    public function setUnreadData($unreadData, $isOutOfBand = false)
    {
        $this->unreadData[(int) (bool) $isOutOfBand] = $unreadData;
    }

    /**
     * Resets socket context
     *
     * @return void
     */
    public function reset()
    {
        $this->unreadData = ['', ''];
    }
}
