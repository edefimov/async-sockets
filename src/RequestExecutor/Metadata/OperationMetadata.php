<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Metadata;

use AsyncSockets\Socket\SocketInterface;

/**
 * Class OperationMetadata
 */
class OperationMetadata
{
    /**
     * Socket for this operation
     *
     * @var SocketInterface
     */
    private $socket;

    /**
     * Key-value pairs with meta information
     *
     * @var array
     */
    private $metadata;

    /**
     * Array of callables for this socket indexed by event name
     *
     * @var HandlerBag
     */
    private $subscribers;

    /**
     * Flag whether to stop this operation
     *
     * @var bool
     */
    private $isOperationCancelled;

    /**
     * Flag whether this socket request is running
     *
     * @var bool
     */
    private $isRunning;

    /**
     * OperationMetadata constructor.
     *
     * @param SocketInterface $socket Socket object
     * @param array           $metadata Metadata
     */
    public function __construct(SocketInterface $socket, array $metadata)
    {
        $this->socket      = $socket;
        $this->metadata    = $metadata;
        $this->subscribers = new HandlerBag();
        $this->initialize();
    }

    /**
     * Initialize data before request
     *
     * @return void
     */
    public function initialize()
    {
        $this->isOperationCancelled = false;
        $this->isRunning            = false;
    }

    /**
     * Return flag whether request is running
     *
     * @return boolean
     */
    public function isRunning()
    {
        return $this->isRunning;
    }

    /**
     * Sets running flag
     *
     * @param boolean $isRunning New value for IsRunning
     *
     * @return void
     */
    public function setRunning($isRunning)
    {
        $this->isRunning = $isRunning;
    }

    /**
     * Return isOperationCancelled
     *
     * @return boolean
     */
    public function isOperationCancelled()
    {
        return $this->isOperationCancelled;
    }

    /**
     * Sets IsOperationCancelled
     *
     * @param boolean $isOperationCancelled New value for IsOperationCancelled
     *
     * @return void
     */
    public function setOperationCancelled($isOperationCancelled)
    {
        $this->isOperationCancelled = $isOperationCancelled;
    }

    /**
     * Return Socket
     *
     * @return SocketInterface
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Return key-value array with metadata
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set metadata for given socket
     *
     * @param string|array    $key Either string or key-value array of metadata. If string, then value must be
     *                             passed in third argument, if array, then third argument will be ignored
     * @param mixed           $value Value for key or null, if $key is array
     *
     * @return void
     */
    public function setMetadata($key, $value = null)
    {
        if (!is_array($key)) {
            $this->metadata[$key] = $value;
        } else {
            $this->metadata = array_merge(
                $this->metadata,
                $key
            );
        }
    }

    /**
     * Return Subscribers
     *
     * @return HandlerBag
     */
    public function getSubscribers()
    {
        return $this->subscribers;
    }
}
