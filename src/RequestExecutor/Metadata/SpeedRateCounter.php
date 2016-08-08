<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Metadata;

/**
 * Class SpeedRateCounter
 */
class SpeedRateCounter
{
    /**
     * Array of measurements
     *
     * @var array
     */
    private $measures = [];

    /**
     * Amount of measures to take
     *
     * @var int
     */
    private $maxMeasures;

    /**
     * Minimum amount of measures
     *
     * @var int
     */
    private $minMeasures;

    /**
     * Current min value
     *
     * @var double
     */
    private $currentSpeed;

    /**
     * Duration of speed below min in seconds
     *
     * @var int
     */
    private $currentDuration;

    /**
     * Minimum allowed speed in bytes per second
     *
     * @var float
     */
    private $minSpeed;

    /**
     * Maximum duration of minimum speed in seconds
     *
     * @var float
     */
    private $maxDuration;

    /**
     * SpeedRateCounter constructor.
     *
     * @param double $minSpeed Minimum allowed speed in bytes per second
     * @param double $maxDuration Maximum duration of minimum speed in seconds
     */
    public function __construct($minSpeed, $maxDuration)
    {
        $this->maxMeasures = 2;
        $this->minMeasures = 2;
        $this->minSpeed    = $minSpeed;
        $this->maxDuration = $maxDuration;
        $this->reset();
    }

    /**
     * Resets this counter
     *
     * @return void
     */
    public function reset()
    {
        $this->measures        = [];
        $this->currentSpeed    = 0;
        $this->currentDuration = 0;
    }

    /**
     * Process next measurement
     *
     * @param double $time Time in seconds
     * @param double $value Amount of received data in bytes
     *
     * @return void
     * @throws \OverflowException When speed is slower then desired
     */
    public function advance($time, $value)
    {
        $this->addMeasure($time, $value);
        $this->currentSpeed = $this->getAverageSpeed();
        if ($this->minSpeed === null || $this->maxDuration === null) {
            return;
        }

        if ($this->currentSpeed !== null) {
            $this->currentDuration = $this->currentSpeed < $this->minSpeed ?
                $this->currentDuration + $this->getElapsedTime() :
                0;

            if ($this->currentDuration > $this->maxDuration) {
                throw new \OverflowException();
            }
        }
    }

    /**
     * Adds measure for counter
     *
     * @param double $time Time for given value in absolute timestamp
     * @param double $value A value
     *
     * @return void
     */
    private function addMeasure($time, $value)
    {
        $this->measures[] = [ $time, $value ];
        if (count($this->measures) > $this->maxMeasures) {
            array_shift($this->measures);
        }
    }

    /**
     * Return average speed for measurements
     *
     * @return double|null
     */
    private function getAverageSpeed()
    {
        $elapsed = $this->getElapsedTime();
        $end     = end($this->measures);

        return $elapsed ? $end[1] / $elapsed : null;
    }

    /**
     * Return elapsed time for average value
     *
     * @return double
     */
    private function getElapsedTime()
    {
        if (count($this->measures) < $this->minMeasures) {
            return null;
        }

        $end   = end($this->measures);
        $start = reset($this->measures);

        return $end[0] - $start[0];
    }

    /**
     * Return current speed in bytes per seconds
     *
     * @return float
     */
    public function getCurrentSpeed()
    {
        return $this->getAverageSpeed();
    }

    /**
     * Return duration of current slow speed
     *
     * @return int
     */
    public function getCurrentDuration()
    {
        return $this->currentDuration;
    }
}
