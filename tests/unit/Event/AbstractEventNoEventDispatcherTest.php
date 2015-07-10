<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Event;

use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class AbstractEventTest
 */
class AbstractEventNoEventDispatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testEventInstanceOf
     *
     * @return void
     */
    public function testEventInstanceOf()
    {
        $event = $this->getMockBuilder('AsyncSockets\Event\AbstractEvent')->getMock();
        self::assertNotInstanceOf(
            'Symfony\Component\EventDispatcher\Event',
            $event,
            'Fail to work properly without event dispatcher'
        );
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        PhpFunctionMocker::getPhpFunctionMocker('class_alias')->setCallable(
            function ($class, $alias, $autoload = true) {
                if ($class === 'Symfony\Component\EventDispatcher\Event') {
                    return false;
                }

                return \class_alias($class, $alias, $autoload);
            }
        );
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        PhpFunctionMocker::getPhpFunctionMocker('class_alias')->restoreNativeHandler();
    }
}
