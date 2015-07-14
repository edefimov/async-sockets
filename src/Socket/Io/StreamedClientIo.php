<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Socket\Io;

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

    /** {@inheritdoc} */
    protected function readRawData()
    {
        // work-around https://bugs.php.net/bug.php?id=52602
        $resource = $this->socket->getStreamResource();
        $result   = '';

        do {
            $data = fread($resource, self::SOCKET_BUFFER_SIZE);
            $this->throwNetworkSocketExceptionIf($data === false, 'Failed to read data.');
            $result     .= $data;
            $isDataEmpty = $data === '';

            $this->readAttempts = $isDataEmpty ?
                $this->readAttempts - 1 :
                self::READ_ATTEMPTS;
        } while (!$isDataEmpty);

        return $result;
    }

    /** {@inheritdoc} */
    protected function writeRawData($data)
    {
        $resource = $this->socket->getStreamResource();
        $test     = stream_socket_sendto($resource, '');
        $this->throwNetworkSocketExceptionIf($test !== 0, 'Failed to send data.');

        $written = fwrite($resource, $data, strlen($data));
        $this->throwNetworkSocketExceptionIf($written === false, 'Failed to send data.');

        if ($written === 0) {
            $this->throwExceptionIfNotConnected('Remote connection has been lost.');
        }

        return $written;
    }

    /** {@inheritdoc} */
    protected function isConnected()
    {
        $name = stream_socket_get_name($this->socket->getStreamResource(), true);
        return $name !== false;
    }

    /** {@inheritdoc} */
    protected function isEndOfTransfer()
    {
        $resource = $this->socket->getStreamResource();
        return stream_socket_recvfrom($resource, 1, MSG_PEEK) === '';
    }

    /** {@inheritdoc} */
    protected function canReachFrame()
    {
        return $this->readAttempts > 0;
    }
}
