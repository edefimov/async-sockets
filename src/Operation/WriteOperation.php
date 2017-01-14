<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Operation;

/**
 * Class WriteOperation
 */
class WriteOperation implements OperationInterface
{
    /**
     * Data to write
     *
     * @var string
     */
    private $data;

    /**
     * Flag if this is an out-of-band writing
     *
     * @var bool
     */
    private $isOutOfBand;

    /**
     * WriteOperation constructor.
     *
     * @param string $data Data to send
     * @param bool   $isOutOfBand Flag if this is an out-of-band writing
     */
    public function __construct($data = null, $isOutOfBand = false)
    {
        $this->data         = $data !== null ? (string) $data : null;
        $this->isOutOfBand = $isOutOfBand;
    }

    /**
     * Return flag if this is an out-of-band writing
     *
     * @return bool
     */
    public function isOutOfBand()
    {
        return $this->isOutOfBand;
    }

    /**
     * Set out-of-band flag
     *
     * @param bool $isOutOfBand Flag if this is an out-of-band writing
     *
     * @return void
     */
    public function setOutOfBand($isOutOfBand)
    {
        $this->isOutOfBand = $isOutOfBand;
    }

    /** {@inheritdoc} */
    public function getType()
    {
        return self::OPERATION_WRITE;
    }

    /**
     * Return Data
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets Data
     *
     * @param string $data Data to send
     *
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Checks whether request has data
     *
     * @return bool
     */
    public function hasData()
    {
        return $this->data !== null;
    }

    /**
     * Clear send data
     *
     * @return void
     */
    public function clearData()
    {
        $this->data = null;
    }
}
