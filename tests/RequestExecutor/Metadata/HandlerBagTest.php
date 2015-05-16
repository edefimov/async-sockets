<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\RequestExecutor\Metadata\HandlerBag;

/**
 * Class HandlerBagTest
 */
class HandlerBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Handler bag
     *
     * @var HandlerBag
     */
    private $bag;

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->bag = new HandlerBag();
    }

    /**
     * testEmpty
     *
     * @return void
     */
    public function testEmpty()
    {
        self::assertCount(0, $this->bag->getHandlersFor(md5(microtime())));
    }

    /**
     * testAddHandler
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @dataProvider callableDataProvider
     */
    public function testAddHandler($callables)
    {
        $this->bag->addHandler(
            [
                'test' => $callables
            ]
        );

        $added    = $this->bag->getHandlersFor('test');
        $expected = (array) $callables;
        self::assertCount(count($expected), $added, 'Unexpected callable result returnes');
        foreach ($expected as $idx => $callable) {
            self::assertNotFalse(
                array_search($callable, $added, true),
                'Function at index ' . $idx . ' was not found'
            );
        }
    }

    /**
     * testRemoveHandler
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @depends testAddHandler
     * @dataProvider callableDataProvider
     */
    public function testRemoveHandler($callables)
    {
        $this->bag->addHandler(
            [
                'test' => $callables,
                'keep' => $callables
            ]
        );

        $this->bag->removeHandler(['test' => $callables]);

        self::assertCount(0, $this->bag->getHandlersFor('test'));
        self::assertCount(count((array) $callables), $this->bag->getHandlersFor('keep'));
    }

    /**
     * testRemoveFromEmpty
     *
     * @param callable|callable[] Array of callables
     *
     * @return void
     * @depends testAddHandler
     * @dataProvider callableDataProvider
     */
    public function testRemoveFromEmpty($callables)
    {
        $this->bag->removeHandler(
            [
                md5(microtime()) => $callables
            ]
        );
    }

    /**
     * callableDataProvider
     *
     * @return array
     */
    public function callableDataProvider()
    {
        $func = function () {

        };
        return [
            [ $func ],
            [ [$func, clone $func ] ]
        ];
    }
}
