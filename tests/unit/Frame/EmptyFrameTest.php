<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\EmptyFrame;

/**
 * Class EmptyFrameTest
 */
class EmptyFrameTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testState
     *
     * @return void
     */
    public function testState()
    {
        $remoteAddress = sha1(microtime());
        $object        = new EmptyFrame($remoteAddress);

        self::assertSame('', (string) $object, 'Incorrect casting to string');
        self::assertSame('', $object->getData(), 'Incorrect data returned');
        self::assertSame($remoteAddress, $object->getRemoteAddress(), 'Incorrect remote address');
    }
}
