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
 * Class PartialSocketResponse
 */
class PartialSocketResponse extends SocketResponse
{
    /**
     * Next parts of this response
     *
     * @var SocketResponse[]
     */
    private $parts = [];

    /**
     * Add response into list of parts
     *
     * @param SocketResponse $response Response object
     *
     * @return void
     */
    public function addResponse(SocketResponse $response)
    {
        $this->parts[] = $response;
    }

    /** {@inheritdoc} */
    public function getData()
    {
        $result = parent::getData();
        foreach ($this->parts as $response) {
            $result .= $response->getData();
        }

        return $result;
    }
}
