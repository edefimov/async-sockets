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
     * @param OperationInterface $nextOperation I/O operation after handshake will complete
     * @param int                $cipher Cipher to use for SSL encryption
     */
    public function __construct(OperationInterface $nextOperation = null, $cipher = STREAM_CRYPTO_METHOD_TLS_CLIENT)
    {
        $this->nextOperation = $nextOperation;
        $this->cipher        = $cipher;
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
