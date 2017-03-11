<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor;

use AsyncSockets\RequestExecutor\ExecutionContext;

/**
 * Class ExecutionContextTest
 */
class ExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test object
     *
     * @var ExecutionContext
     */
    private $object;

    /**
     * testOperatingWithKeys
     *
     * @return void
     */
    public function testOperatingWithKeys()
    {
        $key = md5(microtime());
        self::assertFalse($this->object->has($key), 'Key shouldn\'t be present after object initialization');
        self::assertFalse($this->object->offsetExists($key), 'Key shouldn\'t be present after object initialization');
        self::assertFalse(isset($this->object[$key]), 'Key shouldn\'t be present after object initialization');

        $value = sha1(microtime());
        $this->object->set($key, $value);
        self::assertTrue($this->object->has($key), 'Key must be present after adding value');
        self::assertTrue($this->object->offsetExists($key), 'Key must be present after adding value');
        self::assertTrue(isset($this->object[$key]), 'Key must be present after adding value');

        self::assertSame($value, $this->object->get($key), 'Incorrect value');
        self::assertSame($value, $this->object->offsetGet($key), 'Incorrect value');
        self::assertSame($value, $this->object[$key], 'Incorrect value');

        $this->object->remove($key);
        self::assertFalse($this->object->has($key), 'Key has not been removed');
        self::assertFalse($this->object->offsetExists($key), 'Key has not been removed');
        self::assertFalse(isset($this->object[$key]), 'Key has not been removed');

        self::assertSame(2, $this->object->get($key, 2), 'Default value mismatch');
    }

    /**
     * testNestedNamespace
     *
     * @return void
     */
    public function testNestedNamespace()
    {
        $key    = md5(microtime());
        $nested = $this->object->inNamespace(__FUNCTION__);
        $nested->set($key, 1);

        self::assertFalse($this->object->has($key), 'Key shouldn\'t be present in root context');
        self::assertSame(1, $nested->get($key), 'Incorrect value');
    }

    /**
     * testClearContext
     *
     * @return void
     */
    public function testClearContext()
    {
        $key = md5(microtime());
        $this->object->set($key, 1);
        $this->object->inNamespace(__FUNCTION__)->set($key, 2);
        $this->object->clear();

        self::assertFalse($this->object->has($key), 'Key is not removed from root object');
        self::assertTrue($this->object->inNamespace(__FUNCTION__)->has($key), 'Key from nested namespace must be preserved');
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->object = new ExecutionContext();
    }
}
