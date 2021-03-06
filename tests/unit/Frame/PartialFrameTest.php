<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Frame;

use AsyncSockets\Frame\FrameInterface;
use AsyncSockets\Frame\PartialFrame;

/**
 * Class PartialFrameTest
 */
class PartialFrameTest extends FrameTest
{
    /** {@inheritdoc} */
    protected function createFrame($data)
    {
        $mock = $this->getMockBuilder('AsyncSockets\Frame\FrameInterface')
                ->setMethods(['getData', '__toString', 'getRemoteAddress'])
                ->getMockForAbstractClass();

        $mock->expects(self::any())->method('getData')->willReturn($data);
        $mock->expects(self::any())->method('__toString')->willReturn($data);
        $mock->expects(self::any())->method('getRemoteAddress')->willReturn($data);

        /** @var FrameInterface $mock */
        return new PartialFrame($mock);
    }
}
