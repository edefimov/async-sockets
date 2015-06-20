<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\Frame;
use AsyncSockets\Frame\FrameInterface;

/**
 * Class FrameTest
 */
class FrameTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create response object
     *
     * @param string $data Data for response
     *
     * @return FrameInterface
     */
    protected function createResponse($data)
    {
        return new Frame($data);
    }

    /**
     * testGetData
     *
     * @return void
     */
    public function testGetData()
    {
        $data  = md5(microtime());
        $frame = $this->createResponse($data);
        self::assertEquals($data, $frame->data(), 'Get data failed');
        self::assertEquals($data, (string) $frame, 'String casting failed');
    }
}
