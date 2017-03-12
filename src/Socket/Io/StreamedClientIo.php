<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

use AsyncSockets\Exception\DisconnectException;
use AsyncSockets\Exception\NetworkSocketException;
use AsyncSockets\Exception\RecvDataException;
use AsyncSockets\Exception\SendDataException;
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
    protected function readRawDataIntoPicker(FramePickerInterface $picker, $isOutOfBand)
    {
        return $isOutOfBand ? $this->readOobData($picker) : $this->readRegularData($picker);
    }

    /** {@inheritdoc} */
    protected function writeRawData($data, $isOutOfBand)
    {
        $resource = $this->socket->getStreamResource();
        $this->verifySendResult('', stream_socket_sendto($resource, ''));

        $written = $isOutOfBand ?
            $this->writeOobData($resource, $data) :
            fwrite($resource, $data, strlen($data));

        $this->verifySendResult($data, $written);

        return $written;
    }

    /**
     * Verifies that send operation completed successfully
     *
     * @param string   $data Data for remote side
     * @param int|bool $sendResult Return value from send function
     *
     * @return void
     * @throws NetworkSocketException
     */
    private function verifySendResult($data, $sendResult)
    {
        if ($sendResult === false || $sendResult < 0) {
            throw new SendDataException(
                $this->socket,
                trim('Failed to send data. ' . $this->getLastPhpErrorMessage())
            );
        }

        if ($sendResult === 0 && !empty($data) && !$this->isConnected()) {
            throw DisconnectException::lostRemoteConnection($this->socket);
        }
    }

    /** {@inheritdoc} */
    public function isConnected()
    {
        return $this->resolveRemoteAddress() !== null;
    }

    /** {@inheritdoc} */
    protected function getRemoteAddress()
    {
        if ($this->remoteAddress === null) {
            $this->remoteAddress = $this->resolveRemoteAddress();
            if ($this->remoteAddress === null) {
                throw DisconnectException::lostRemoteConnection($this->socket);
            }
        }

        return $this->remoteAddress;
    }

    /** {@inheritdoc} */
    protected function canReachFrame()
    {
        return $this->readAttempts > 0 && $this->isConnected();
    }

    /**
     * Read OOB data from socket
     *
     * @param FramePickerInterface $picker
     *
     * @return string
     */
    private function readOobData(FramePickerInterface $picker)
    {
        $data = stream_socket_recvfrom(
            $this->socket->getStreamResource(),
            self::SOCKET_BUFFER_SIZE,
            STREAM_OOB
        );

        return $picker->pickUpData($data, $this->getRemoteAddress());
    }

    /**
     * Read regular data
     *
     * @param FramePickerInterface $picker Picker to read data into
     *
     * @return string
     */
    private function readRegularData(FramePickerInterface $picker)
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
            if ($data === false) {
                throw new RecvDataException(
                    $this->socket,
                    trim('Failed to read data. ' . $this->getLastPhpErrorMessage())
                );
            }

            $isDataEmpty = $data === '';
            $result      = $picker->pickUpData($data, $this->getRemoteAddress());

            $readContext['countCycles']      += 1;
            $readContext['isStreamDataEmpty'] = $this->isReadDataActuallyEmpty($data);
            $this->readAttempts               = $this->resolveReadAttempts($readContext, $this->readAttempts);
        } while (!$picker->isEof() && !$isDataEmpty);

        return $result;
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
            $this->verifySendResult($data[$i], $written);

            if ($written === 0) {
                break;
            }

            $result += $written;
        }

        return $result;
    }

    /**
     * Return remote address if we connected or false otherwise
     *
     * @return string|null
     */
    private function resolveRemoteAddress()
    {
        $result = stream_socket_get_name($this->socket->getStreamResource(), true);

        return $result !== false ? $result : null;
    }
}
