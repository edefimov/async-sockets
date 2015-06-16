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
class SocketResponse extends AbstractSocketResponse
{
    /**
     * Data from network for this object
     *
     * @var string
     */
    private $data;

    /**
     * SocketResponse constructor.
     *
     * @param string $data Data from network for this response
     */
    public function __construct($data)
    {
        $this->data = (string) $data;
    }

    /** {@inheritdoc} */
    public function getData()
    {
        return $this->data;
    }
}
