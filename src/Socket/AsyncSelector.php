<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Exception\SocketException;
use AsyncSockets\Exception\TimeoutException;
use AsyncSockets\RequestExecutor\OperationInterface;

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
        $attempts = floor(($seconds * 1E6 + $usec) / self::ATTEMPT_DELAY) + 1;

        do {
            $result = $this->doStreamSelect($seconds, $usec, $read, $write);
            if ($result === false) {
                throw new SocketException('Failed to select sockets');
            }

            if ($result === 0) {
                break;
            }

            $readyRead  = $this->popSocketsByResources((array) $read, OperationInterface::OPERATION_READ);
            $readyWrite = $this->popSocketsByResources((array) $write, OperationInterface::OPERATION_WRITE);

            if ($readyRead || $readyWrite) {
                return new SelectContext($readyRead, $readyWrite);
            }

            $attempts -= 1;
            if ($attempts) {
                usleep(self::ATTEMPT_DELAY);
            }
        } while ($attempts);

        throw new TimeoutException('Select operation was interrupted during timeout');
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
     * Get socket objects by resources and remove them from work list
     *
     * @param resource[] $resources Stream resources
     * @param string     $operation One of OperationInterface::OPERATION_* consts
     *
     * @return StreamResourceInterface[]
     */
    private function popSocketsByResources(array $resources, $operation)
    {
        if (!$resources || !isset($this->streamResources[$operation])) {
            return [];
        }

        $result = [];
        foreach ($this->streamResources[$operation] as $socket) {
            /** @var StreamResourceInterface $socket */
            $isReadySocket = in_array($socket->getStreamResource(), $resources, true) &&
                             $this->isActuallyReadyForIo($socket->getStreamResource(), $operation);
            if ($isReadySocket) {
                $this->removeSocketOperation($socket, $operation);
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
     *
     * @return bool
     */
    private function isActuallyReadyForIo($stream, $operation)
    {
        return (
            $operation === OperationInterface::OPERATION_READ &&

            // https://bugs.php.net/bug.php?id=65137
            stream_socket_recvfrom($stream, 1, MSG_PEEK) !== false
        ) || (
            $operation === OperationInterface::OPERATION_WRITE
        );
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
     * Make stream_select call
     *
     * @param int $seconds Amount of seconds to wait
     * @param int $usec Amount of microseconds to add to $seconds
     * @param resource[] $read List of sockets to check for read. After function return it will be filled with
     *      sockets, which are ready to read
     * @param resource[] $write List of sockets to check for write. After function return it will be filled with
     *      sockets, which are ready to write
     *
     * @return bool|int False in case of system error, int - amount of sockets ready for I/O
     */
    private function doStreamSelect($seconds, $usec = null, array &$read = null, array &$write = null)
    {
        $except = null;
        $result = stream_select($read, $write, $except, $seconds, $usec);

        return $result === false ? $result : count($read) + count($write);
    }
}
