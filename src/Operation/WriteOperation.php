<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
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
     * WriteOperation constructor.
     *
     * @param string $data Data to send
     */
    public function __construct($data = null)
    {
        $this->data = $data !== null ? (string) $data : null;
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
