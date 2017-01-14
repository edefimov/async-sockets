<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
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
     * Amount of processed bytes
     *
     * @var int
     */
    private $totalBytesProcessed;

    /**
     * Time when request is started
     *
     * @var int
     */
    private $initialTime;

    /**
     * Time of last measurement
     *
     * @var int
     */
    private $currentTime;

    /**
     * Time when speed felt below minimal
     *
     * @var int
     */
    private $slowStartTime;

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
     * Current speed
     *
     * @var double
     */
    private $currentSpeed;

    /**
     * SpeedRateCounter constructor.
     *
     * @param double $minSpeed Minimum allowed speed in bytes per second
     * @param double $maxDuration Maximum duration of minimum speed in seconds
     */
    public function __construct($minSpeed, $maxDuration)
    {
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
        $this->initialTime         = null;
        $this->currentTime         = null;
        $this->totalBytesProcessed = 0;
        $this->currentSpeed        = 0;
        $this->slowStartTime       = null;
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
        $this->measure($time, $value);
        $this->currentSpeed = $this->getAverageSpeed();
        if ($this->minSpeed === null || $this->maxDuration === null || $this->currentSpeed === null) {
            return;
        }

        if ($this->currentSpeed < $this->minSpeed) {
            $this->slowStartTime = $this->slowStartTime !== null ? $this->slowStartTime : $time;

            if ($time - $this->slowStartTime > $this->maxDuration) {
                throw new \OverflowException();
            }
        } else {
            $this->slowStartTime = null;
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
    private function measure($time, $value)
    {
        if ($this->initialTime === null) {
            $this->initialTime = $time;
        } else {
            $this->currentTime = $time;
        }

        $this->totalBytesProcessed += $value;
    }

    /**
     * Return average speed for measurements
     *
     * @return double|null
     */
    private function getAverageSpeed()
    {
        $timeElapsed = $this->currentTime - $this->initialTime;

        return $timeElapsed >= 1 ? ($this->totalBytesProcessed / $timeElapsed) : 0;
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
        return $this->slowStartTime !== null ? $this->currentTime - $this->slowStartTime : 0;
    }
}
