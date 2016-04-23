<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Frame\FramePickerInterface;

/**
 * Class StreamedClientIo
 */
class StreamedClientIo extends AbstractClientIo
{
    /**
     * Read attempts count
     */
    const READ_ATTEMPTS = 2;

    /**
     * Amount of read attempts
     *
     * @var int
     */
    private $readAttempts = self::READ_ATTEMPTS;

    /**
     * Remote socket address
     *
     * @var string
     */
    private $remoteAddress;

    /** {@inheritdoc} */
    protected function readRawDataIntoPicker(FramePickerInterface $picker)
    {
        // work-around https://bugs.php.net/bug.php?id=52602
        $resource         = $this->socket->getStreamResource();
        $readContext      = [
            'countCycles'       => 0,
            'dataBeforeIo'      => $this->getDataInSocket(),
            'isStreamDataEmpty' => false,
        ];

        do {
            $data = fread($resource, self::SOCKET_BUFFER_SIZE);
            $this->throwNetworkSocketExceptionIf($data === false, 'Failed to read data.', true);
            $isDataEmpty = $data === '';
            $result      = $picker->pickUpData($data, $this->getRemoteAddress());

            $readContext['countCycles']      += 1;
            $readContext['isStreamDataEmpty'] = $this->isReadDataActuallyEmpty($data);
            $this->readAttempts               = $this->resolveReadAttempts($readContext, $this->readAttempts);
        } while (!$picker->isEof() && !$isDataEmpty);

        return $result;
    }

    /**
     * Calculate attempts value
     *
     * @param array $context Read context
     * @param int   $currentAttempts Current attempts counter
     *
     * @return int
     */
    private function resolveReadAttempts(array $context, $currentAttempts)
    {
        return ($context['countCycles'] === 1 && empty($context['dataBeforeIo'])) ||
               ($context['countCycles'] > 1   && $context['isStreamDataEmpty']) ?
            $currentAttempts - 1 :
            self::READ_ATTEMPTS;

    }

    /** {@inheritdoc} */
    protected function writeRawData($data, $isOutOfBand)
    {
        $resource = $this->socket->getStreamResource();
        $test     = stream_socket_sendto($resource, '');
        $this->throwNetworkSocketExceptionIf($test !== 0, 'Failed to send data.', true);

        $written = $isOutOfBand ?
            $this->writeOobData($resource, $data) :
            fwrite($resource, $data, strlen($data));

        $this->throwNetworkSocketExceptionIf($written === false, 'Failed to send data.', true);

        if ($written === 0) {
            $this->throwExceptionIfNotConnected('Remote connection has been lost.');
        }

        return $written;
    }

    /** {@inheritdoc} */
    protected function isConnected()
    {
        return $this->resolveRemoteAddress() !== false;
    }

    /** {@inheritdoc} */
    protected function getRemoteAddress()
    {
        if ($this->remoteAddress === null) {
            $this->remoteAddress = $this->resolveRemoteAddress();
        }

        return $this->remoteAddress;
    }

    /**
     * Checks whether data read from stream buffer can be filled later
     *
     * @param string $data Read data
     *
     * @return bool
     */
    private function isReadDataActuallyEmpty($data)
    {
        $result = false;
        if ($data === '') {
            $dataInSocket = $this->getDataInSocket();
            $result       = $dataInSocket === '' || $dataInSocket === false;
        }

        return $result;
    }

    /** {@inheritdoc} */
    protected function canReachFrame()
    {
        return $this->readAttempts > 0 && $this->isConnected();
    }

    /**
     * Return first byte from socket buffer
     *
     * @return string
     */
    private function getDataInSocket()
    {
        return stream_socket_recvfrom($this->socket->getStreamResource(), 1, STREAM_PEEK);
    }

    /**
     * Return remote address if we connected or false otherwise
     *
     * @return string|bool
     */
    private function resolveRemoteAddress()
    {
        return stream_socket_get_name($this->socket->getStreamResource(), true);
    }

    /**
     * Write out-of-band data
     *
     * @param resource $socket Socket resource
     * @param string   $data Data to write
     *
     * @return int Amount of written bytes
     */
    private function writeOobData($socket, $data)
    {
        $result     = 0;
        $dataLength = strlen($data);
        for ($i = 0; $i < $dataLength; $i++) {
            $written = stream_socket_sendto($socket, $data[$i], STREAM_OOB);
            $this->throwNetworkSocketExceptionIf($written < 0, 'Failed to send data.', true);
            if ($written === 0) {
                break;
            }

            $result += $written;
        }

        return $result;
    }
}
