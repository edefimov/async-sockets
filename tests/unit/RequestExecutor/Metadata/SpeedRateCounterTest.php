<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\RequestExecutor\Metadata\SpeedRateCounter;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class SpeedRateCounterTest
 */
class SpeedRateCounterTest extends AbstractTestCase
{
    /**
     * testCalculateAverage
     *
     * @param array  $measures List of measures
     * @param double $minSpeed Minimum allowed speed
     * @param double $maxDuration Maximum duration of minimum speed
     * @param double $expectedSpeed Speed at the end of test
     * @param double $expectedDuration Duration at the end of test
     * @param double $accuracy Expected value precision
     *
     * @dataProvider averageRawValuesProvider
     */
    public function testCalculateAverage(
        array $measures,
        $minSpeed,
        $maxDuration,
        $expectedSpeed,
        $expectedDuration,
        $accuracy
    ) {
        $object = new SpeedRateCounter($minSpeed, $maxDuration);
        foreach ($measures as $measure) {
            $object->advance(reset($measure), end($measure));
        }

        $currentSpeed = $object->getCurrentSpeed();
        self::assertGreaterThanOrEqual($expectedSpeed - $accuracy, $currentSpeed, 'Incorrect speed lower boundary');
        self::assertLessThanOrEqual($expectedSpeed + $accuracy, $currentSpeed, 'Incorrect speed higher boundary');

        $currentDuration = $object->getCurrentDuration();
        self::assertSame($expectedDuration, $currentDuration, 'Incorrect duration');
    }

    /**
     * testThatExceptionWillBeThrownAfterDurationReached
     *
     * @return void
     * @expectedException \OverflowException
     */
    public function testThatExceptionWillBeThrownAfterDurationReached()
    {
        $minSpeed    = mt_rand(1000, 2000);
        $maxDuration = mt_rand(1000, 2000);

        $object = new SpeedRateCounter($minSpeed, $maxDuration);
        $object->advance(0, 0);
        $object->advance($maxDuration + 1, $minSpeed - 1);
    }

    /**
     * testResetCounter
     *
     * @return void
     */
    public function testResetCounter()
    {
        $minSpeed    = mt_rand(1000, 2000);
        $maxDuration = mt_rand(1000, 2000);

        $object = new SpeedRateCounter($minSpeed, $maxDuration);
        $object->advance(0, 0);
        $object->advance($maxDuration, $minSpeed);

        $object->reset();
        self::assertSame(0.0, $object->getCurrentDuration(), 'Incorrect duration');
        self::assertSame(0.0, $object->getCurrentSpeed(), 'Incorrect speed');
    }

    /**
     * averageRawValuesProvider
     *
     * @param string $targetMethod Destination mehtod
     *
     * @return array
     */
    public function averageRawValuesProvider($targetMethod)
    {
        return $this->dataProviderFromYaml(
            __DIR__,
            __CLASS__,
            __FUNCTION__,
            $targetMethod
        );
    }
}
