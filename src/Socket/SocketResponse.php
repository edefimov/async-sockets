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
 * Class SocketResponse
 */
class SocketResponse
{
    /**
     * Data from network
     *
     * @var string
     */
    private $data;

    /**
     * SocketResponse constructor.
     *
     * @param string $data Data from network
     */
    public function __construct($data)
    {
        $this->data = (string) $data;
    }

    /**
     * Return original data as it was on creation
     *
     * @return string
     */
    protected function getOriginalData()
    {
        return $this->data;
    }

    /**
     * Return full received data
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * __toString
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getData();
    }
}
