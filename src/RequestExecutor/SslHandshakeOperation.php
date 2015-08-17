<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor;

/**
 * Class SslHandshakeOperation
 */
class SslHandshakeOperation implements OperationInterface
{
    /**
     * Cipher to use for SSL encryption
     *
     * @var int
     */
    private $cipher;

    /**
     * I/O operation after handshake will complete
     *
     * @var OperationInterface
     */
    private $nextOperation;

    /**
     * SslHandshakeOperation constructor.
     *
     * @param int                $cipher Cipher to use for SSL encryption
     * @param OperationInterface $nextOperation I/O operation after handshake will complete
     */
    public function __construct($cipher = STREAM_CRYPTO_METHOD_TLS_CLIENT, OperationInterface $nextOperation = null)
    {
        $this->cipher = $cipher;
        $this->nextOperation = $nextOperation;
    }

    /** {@inheritdoc} */
    public function getType()
    {
        return self::OPERATION_WRITE;
    }

    /**
     * Return Cipher
     *
     * @return int
     */
    public function getCipher()
    {
        return $this->cipher;
    }

    /**
     * Return NextOperation
     *
     * @return OperationInterface
     */
    public function getNextOperation()
    {
        return $this->nextOperation;
    }
}
