<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\LibEvent;

use AsyncSockets\RequestExecutor\LibEvent\LeCallbackInterface;
use AsyncSockets\RequestExecutor\LibEvent\LeEvent;
use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use Tests\Application\Mock\PhpFunctionMocker;

/**
 * Class LeEventTest
 */
class LeEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var LeEvent
     */
    private $object;

    /**
     * Callback mock
     *
     * @var LeCallbackInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $callback;

    /**
     * Timeout for test object
     *
     * @var int
     */
    private $timeout;

    /**
     * Metadata object
     *
     * @var OperationMetadata|\PHPUnit_Framework_MockObject_MockObject
     */
    private $metadata;

    /**
     * Event resource
     *
     * @var int
     */
    private $eventResource;

    /**
     * testInitialState
     *
     * @return void
     */
    public function testInitialState()
    {
        self::assertSame($this->timeout, $this->object->getTimeout(), 'Incorrect timeout');
        self::assertSame($this->metadata, $this->object->getOperationMetadata(), 'Incorrect operation metadata');
        self::assertSame($this->eventResource, $this->object->getHandle(), 'Incorrect event resource');
    }

    /**
     * testFireEvent
     *
     * @return void
     */
    public function testFireEvent()
    {
        $type = (string) mt_rand(0, PHP_INT_MAX);
        $this->callback->expects(self::once())->method('onEvent')
            ->with($this->metadata, $type);
        $this->object->fire($type);
    }

    /**
     * testFreeResourceOnDestroy
     *
     * @return void
     */
    public function testFreeResourceOnDestroy()
    {
        $mock = $this->getMockBuilder('Countable')
                    ->setMethods(['count'])
                    ->getMockForAbstractClass();

        $mock->expects(self::exactly(2))->method('count')->with($this->eventResource);
        PhpFunctionMocker::getPhpFunctionMocker('event_del')->setCallable([$mock, 'count']);
        PhpFunctionMocker::getPhpFunctionMocker('event_free')->setCallable([$mock, 'count']);
        $this->object = null;
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        if (!extension_loaded('libevent')) {
            self::markTestSkipped('To pass this test libevent extension must be installed');
        }

        $this->callback = $this->getMockBuilder('AsyncSockets\RequestExecutor\LibEvent\LeCallbackInterface')
                            ->setMethods(['onEvent'])
                            ->getMockForAbstractClass();

        $this->metadata = $this->getMockBuilder('AsyncSockets\RequestExecutor\Metadata\OperationMetadata')
                                ->disableOriginalConstructor()
                                ->getMock();

        $this->timeout       = mt_rand(0, PHP_INT_MAX);
        $this->eventResource = mt_rand(0, PHP_INT_MAX);

        PhpFunctionMocker::getPhpFunctionMocker('event_new')->setCallable(function () {
            return $this->eventResource;
        });

        $emptyFunction = function () {
        };

        PhpFunctionMocker::getPhpFunctionMocker('event_del')->setCallable($emptyFunction);
        PhpFunctionMocker::getPhpFunctionMocker('event_free')->setCallable($emptyFunction);

        $this->object = new LeEvent($this->callback, $this->metadata, $this->timeout);
    }

    /** {@inheritdoc} */
    protected function tearDown()
    {
        parent::tearDown();
        $this->object = null;
        if (extension_loaded('libevent')) {
            PhpFunctionMocker::getPhpFunctionMocker('event_new')->restoreNativeHandler();
            PhpFunctionMocker::getPhpFunctionMocker('event_del')->restoreNativeHandler();
            PhpFunctionMocker::getPhpFunctionMocker('event_free')->restoreNativeHandler();
        }
    }
}
