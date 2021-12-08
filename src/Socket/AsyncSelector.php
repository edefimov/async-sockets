<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\Operation\OperationInterface;

/**
 * Class AsyncSelector
 */
class AsyncSelector
{
    /**
     * Delay in microseconds between select attempts, if previous stream_select returned incorrect result
     * @link https://bugs.php.net/bug.php?id=65137
     */
    const ATTEMPT_DELAY = 250000;

    /**
     * Attempt count to use when time out is not set
     */
    const ATTEMPT_COUNT_FOR_INFINITE_TIMEOUT = 10;

    /**
     * Array of resources indexed by operation
     *
     * @var StreamResourceInterface[][]
     */
    private $streamResources = [];

    /**
     * Wait socket resources for network operation
     *
     * @param int $seconds Number of seconds to wait
     * @param int $usec Number of microseconds to add
     *
     * @return SelectContext
     * @throws TimeoutException If operation was interrupted during timeout
     * @throws SocketException If network operation failed
     * @throws \InvalidArgumentException If there is no socket in the list
     */
    public function select($seconds, $usec = null)
    {
        if (!$this->streamResources) {
            throw new \InvalidArgumentException('Can not perform select on empty data');
        }

        $read     = $this->getSocketsForOperation(OperationInterface::OPERATION_READ);
        $write    = $this->getSocketsForOperation(OperationInterface::OPERATION_WRITE);
        $attempts = $this->calculateAttemptsCount($seconds, $usec);

        do {
            $this->doStreamSelect($seconds, $usec, $read, $write, $oob);

            $context = $this->extractContext((array) $read, (array) $write, (array) $oob);
            if ($context) {
                $this->streamResources = [];
                return $context;
            }

            $attempts -= 1;
            if ($attempts) {
                usleep(self::ATTEMPT_DELAY);
            }
        } while ($attempts);

        throw new TimeoutException('Select operation was interrupted during timeout');
    }

    /**
     * Return socket objects for operations
     *
     * @param string $operation One of OperationInterface::OPERATION_* consts
     *
     * @return resource[]|null List of socket resource
     */
    private function getSocketsForOperation($operation)
    {
        if (!isset($this->streamResources[$operation])) {
            return null;
        }

        $result = [];
        foreach ($this->streamResources[$operation] as $socket) {
            /** @var StreamResourceInterface $socket */
            $result[] = $socket->getStreamResource();
        }

        return $result ?: null;
    }

    /**
     * Calculate amount of attempts for select operation
     *
     * @param int|null $seconds Amount of seconds
     * @param int|null $usec Amount of microseconds
     *
     * @return int
     */
    private function calculateAttemptsCount($seconds, $usec)
    {
        $result = $seconds !== null ? ceil(($seconds * 1E6 + $usec) / self::ATTEMPT_DELAY) :
            self::ATTEMPT_COUNT_FOR_INFINITE_TIMEOUT;
        if ($result < self::ATTEMPT_COUNT_FOR_INFINITE_TIMEOUT) {
            $result = self::ATTEMPT_COUNT_FOR_INFINITE_TIMEOUT;
        }

        return $result;
    }

    /**
     * Make stream_select call
     *
     * @param int        $seconds Amount of seconds to wait
     * @param int        $usec Amount of microseconds to add to $seconds
     * @param resource[] &$read List of sockets to check for read. After function return it will be filled with
     *      sockets, which are ready to read
     * @param resource[] &$write List of sockets to check for write. After function return it will be filled with
     *      sockets, which are ready to write
     * @param resource[] &$oob After call it will be filled with sockets having OOB data, input value is ignored
     *
     * @return int Amount of sockets ready for I/O
     */
    private function doStreamSelect(
        $seconds,
        $usec = null,
        array &$read = null,
        array &$write = null,
        array &$oob = null
    ) {
        $oob    = array_merge((array) $read, (array) $write);
        $result = stream_select($read, $write, $oob, $seconds, $usec);
        if ($result === false) {
            throw new SocketException('Failed to select sockets');
        }

        $result = count($read ?? []) + count($write ?? []) + count($oob);
        if ($result === 0) {
            throw new TimeoutException('Select operation was interrupted during timeout');
        }

        return $result;
    }

    /**
     * Extract context from given lists of resources
     *
     * @param resource[] $read Read-ready resources
     * @param resource[] $write Write-ready resources
     * @param resource[] $oob Oob-ready resources
     *
     * @return SelectContext|null
     */
    private function extractContext(array $read, array $write, array $oob)
    {
        $readyRead  = $this->popSocketsByResources($read, OperationInterface::OPERATION_READ, false);
        $readyWrite = $this->popSocketsByResources($write, OperationInterface::OPERATION_WRITE, false);
        $readyOob   = array_merge(
            $this->popSocketsByResources($oob, OperationInterface::OPERATION_READ, true),
            $this->popSocketsByResources($oob, OperationInterface::OPERATION_WRITE, true)
        );

        if ($readyRead || $readyWrite || $readyOob) {
            return new SelectContext($readyRead, $readyWrite, $readyOob);
        }

        return null;
    }

    /**
     * Get socket objects by resources and remove them from work list
     *
     * @param resource[] $resources Stream resources
     * @param string     $operation One of OperationInterface::OPERATION_* consts
     * @param bool       $isOutOfBand Is it OOB operation
     *
     * @return StreamResourceInterface[]
     */
    private function popSocketsByResources(array $resources, $operation, $isOutOfBand)
    {
        if (!$resources || !isset($this->streamResources[$operation])) {
            return [];
        }

        $result = [];
        foreach ($this->streamResources[$operation] as $socket) {
            /** @var StreamResourceInterface $socket */
            $socketResource = $socket->getStreamResource();
            $isReadySocket  = in_array($socketResource, $resources, true) &&
                                 $this->isActuallyReadyForIo($socketResource, $operation, $isOutOfBand);

            if ($isReadySocket) {
                $result[] = $socket;
            }
        }

        return $result;
    }

    /**
     * Checks whether given socket can process I/O operation after stream_select return
     *
     * @param resource $stream Socket resource
     * @param string   $operation One of OperationInterface::OPERATION_* consts
     * @param bool     $isOutOfBand Is it OOB operation
     *
     * @return bool
     */
    private function isActuallyReadyForIo($stream, $operation, $isOutOfBand)
    {
        /** map[isServer][operation][isOutOfBand] = result */
        $hasOobData = stream_socket_recvfrom($stream, 1, STREAM_PEEK | STREAM_OOB) !== false;
        $map = [
            0 => [
                // https://bugs.php.net/bug.php?id=65137
                OperationInterface::OPERATION_READ => [
                    0 => stream_socket_recvfrom($stream, 1, STREAM_PEEK) !== false,
                    1 => $hasOobData
                ],
                OperationInterface::OPERATION_WRITE => [
                    0 => true,
                    1 => $hasOobData
                ]
            ],
            1 => [
                OperationInterface::OPERATION_READ => [
                    0 => true,
                    1 => $hasOobData
                ],
                OperationInterface::OPERATION_WRITE => [
                    0 => true,
                    1 => $hasOobData
                ]
            ]
        ];

        $serverIdx = (int) (bool) $this->isSocketServer($stream);
        $oobIdx    = (int) (bool) $isOutOfBand;

        return $map[$serverIdx][$operation][$oobIdx];
    }

    /**
     * Check whether given resource is server socket
     *
     * @param resource $resource Resource to test
     *
     * @return bool
     */
    private function isSocketServer($resource)
    {
        return stream_socket_get_name($resource, false) &&
               !stream_socket_get_name($resource, true);
    }

    /**
     * Add array of socket with specified operation
     *
     * @param StreamResourceInterface[] $streamResources List of resources. Value depends on second argument.
     *                                     If string is provided, then it must be array of StreamResourceInterface.
     *                                     If $operation parameter is omitted then this argument must contain
     *                                     pairs [StreamResourceInterface, operation] for each element
     * @param string                    $operation Operation, one of OperationInterface::OPERATION_* consts
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function addSocketOperationArray(array $streamResources, $operation = null)
    {
        foreach ($streamResources as $streamResource) {
            if ($operation !== null) {
                $this->addSocketOperation($streamResource, $operation);
            } else {
                if (!is_array($streamResource) || count($streamResource) !== 2) {
                    throw new \InvalidArgumentException(
                        'First parameter must contain pair (SocketInterface, operation)'
                    );
                }

                $this->addSocketOperation(reset($streamResource), end($streamResource));
            }
        }
    }

    /**
     * Add socket into selector list
     *
     * @param StreamResourceInterface $streamResource Resource object
     * @param string                  $operation One of OperationInterface::OPERATION_* consts
     *
     * @return void
     */
    public function addSocketOperation(StreamResourceInterface $streamResource, $operation)
    {
        $this->streamResources[$operation][spl_object_hash($streamResource)] = $streamResource;
    }

    /**
     * Remove all previously defined operations on this socket and adds socket into list of given operation
     *
     * @param StreamResourceInterface $streamResource Stream resource object
     * @param string                  $operation One of OperationInterface::OPERATION_* consts
     *
     * @return void
     */
    public function changeSocketOperation(StreamResourceInterface $streamResource, $operation)
    {
        $this->removeAllSocketOperations($streamResource);

        $this->addSocketOperation($streamResource, $operation);
    }

    /**
     * Remove given socket from all operations
     *
     * @param StreamResourceInterface $streamResource Resource object
     *
     * @return void
     */
    public function removeAllSocketOperations(StreamResourceInterface $streamResource)
    {
        $opList = [ OperationInterface::OPERATION_READ,
                    OperationInterface::OPERATION_WRITE  ];

        foreach ($opList as $op) {
            $this->removeSocketOperation($streamResource, $op);
        }
    }

    /**
     * Remove given socket from select list
     *
     * @param StreamResourceInterface $streamResource Stream resource object
     * @param string                  $operation One of OperationInterface::OPERATION_* consts
     *
     * @return void
     */
    public function removeSocketOperation(StreamResourceInterface $streamResource, $operation)
    {
        $hash = spl_object_hash($streamResource);
        if (isset($this->streamResources[$operation], $this->streamResources[$operation][$hash])) {
            unset($this->streamResources[$operation][$hash]);
            if (!$this->streamResources[$operation]) {
                unset($this->streamResources[$operation]);
            }
        }
    }
}
