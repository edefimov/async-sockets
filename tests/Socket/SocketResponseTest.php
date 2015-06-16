<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Frame\FramePickerInterface;
use AsyncSockets\Socket\SocketResponse;

/**
 * Class SocketResponseTest
 */
class SocketResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Mocked framePicker
     *
     * @var FramePickerInterface
     */
    protected $frame;

    /**
     * Create response object
     *
     * @param string $data Data for response
     *
     * @return SocketResponse
     */
    protected function createResponse($data)
    {
        return new SocketResponse($data);
    }

    /**
     * testGetData
     *
     * @return void
     */
    public function testGetData()
    {
        $data     = md5(microtime());
        $response = $this->createResponse($data);
        self::assertEquals($data, $response->getData(), 'Get data failed');
        self::assertEquals($data, (string) $response, 'String casting failed');
    }
}
