<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\Pipeline\PushbackIterator;

/**
 * Class PushbackIteratorTest
 */
class PushbackIteratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testDecoratingExistingIterator
     *
     * @return void
     */
    public function testDecoratingExistingIterator()
    {
        $testStr = sha1(microtime());
        $iter    = new \ArrayIterator(str_split($testStr, 1));
        $object  = new PushbackIterator($iter, 1);

        $result = implode('', iterator_to_array($object));
        self::assertSame($testStr, $result, 'Incorrect string after iteration');
    }

    /**
     * testChunkReading
     *
     * @param array $testData  Test data
     * @param int   $chunkSize chunk size
     *
     * @return void
     * @dataProvider chunkTestDataProvider
     */
    public function testChunkReading(array $testData, $chunkSize)
    {
        $testStr = implode('', $testData);
        $iter    = new \ArrayIterator($testData);
        $object  = new PushbackIterator($iter, $chunkSize);

        $result = implode('', iterator_to_array($object));
        self::assertSame($testStr, $result, 'Incorrect string after iteration');
    }

    /**
     * testSimpleUnread
     *
     * @return void
     */
    public function testSimpleUnread()
    {
        $data = sha1(microtime());
        $iter = new PushbackIterator(new \ArrayIterator([$data]), strlen($data));

        $iter->rewind();
        self::assertTrue($iter->valid(), 'Incorrect valid state');

        $read = $iter->current();
        self::assertSame($data, $read, 'Unexpected read result');

        $iter->unread(10);
        $iter->next();
        self::assertTrue($iter->valid(), 'Incorrect valid flag after unread');
        self::assertSame(substr($data, 30, 10), $iter->current(), 'Incorrect unread result');
    }

    /**
     * testUnread
     *
     * @param array  $data Data to send
     * @param int[]  $unread Unread operations
     * @param int    $chunkSize Size of chunk in bytes
     * @param string $expected Expected result
     *
     * @return void
     * @dataProvider unreadDataProvider
     */
    public function testUnread(array $data, array $unread, $chunkSize, $expected)
    {
        $iter = new PushbackIterator(new \ArrayIterator($data), $chunkSize);

        $iter->rewind();
        self::assertTrue($iter->valid(), 'Incorrect valid state');

        foreach ($unread as $length) {
            $iter->unread($length);
        }

        $iter->next();
        self::assertSame($expected, $iter->current(), 'Incorrect unread result');
    }

    /**
     * testNothingHappenWhenBufferEmpty
     *
     * @return void
     */
    public function testNothingHappenWhenBufferEmpty()
    {
        $iter = new PushbackIterator(new \ArrayIterator([]), 1);

        $iter->rewind();
        $iter->unread(1);
        $iter->next();
        self::assertEmpty($iter->current(), 'Unexpected unread result');
    }

    /**
     * chunkTestDataProvider
     *
     * @return array
     */
    public function chunkTestDataProvider()
    {
        return [
            [['11', 222, 3333, '4444'], 2],
            [['11', 222, 3333, '4444'], 3],
            [['11111111111111', 222222222, 33333333333, '444444'], 2],
            [['abcdefghigklmnopqrstuvwxyz'], 2],
        ];
    }

    /**
     * unreadDataProvider
     *
     * @return array
     */
    public function unreadDataProvider()
    {
        return [
            [['123456789012345678901234567890abcdefghij'], [10], 40, 'abcdefghij'],
            [['123456789012345678901234567890abcdefghij'], [10], 2, '12'],
            [['123456789012345678901234567890abcdefghij'], [1, 2, 3, 4], 40, 'abcdefghij'],
            [
                [ '123456789012345678901234567890abcdefghij' ],
                [ 1, 2, 3, 4, 5, 25, 45 ],
                40,
                '123456789012345678901234567890abcdefghij',
            ],
            [['1234567890'], [1], 2, '23'],
        ];
    }
}
