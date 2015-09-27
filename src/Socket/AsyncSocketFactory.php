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

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\RequestExecutor\LibEventRequestExecutor;
use AsyncSockets\RequestExecutor\Pipeline\LibEventStageFactory;
use AsyncSockets\RequestExecutor\Pipeline\NativeStageFactory;
use AsyncSockets\RequestExecutor\Pipeline\PipelineFactory;
use AsyncSockets\RequestExecutor\NativeRequestExecutor;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Class AsyncSocketFactory
 *
 * @api
 */
class AsyncSocketFactory
{
    /**
     * Create client socket
     */
    const SOCKET_CLIENT = 'client';

    /**
     * Create server socket
     */
    const SOCKET_SERVER = 'server';

    /**
     * Default configuration for this factory
     *
     * @var Configuration
     */
    private $configuration;

    /**
     * AsyncSocketFactory constructor.
     *
     * @param Configuration $configuration Default configuration for this factory
     */
    public function __construct(Configuration $configuration = null)
    {
        $this->configuration = $configuration ?: new Configuration();
    }

    /**
     * Create socket client
     *
     * @param string $type Socket type to create, one of SOCKET_* consts
     *
     * @return SocketInterface
     * @throws \InvalidArgumentException If type parameter is unknown
     *
     * @api
     */
    public function createSocket($type = self::SOCKET_CLIENT)
    {
        switch ($type) {
            case self::SOCKET_CLIENT:
                return new ClientSocket();
            case self::SOCKET_SERVER:
                return new ServerSocket();
            default:
                throw new \InvalidArgumentException("Unexpected type {$type} used in " . __FUNCTION__);
        }
    }

    /**
     * Create RequestExecutor object
     *
     * @return RequestExecutorInterface
     *
     * @api
     */
    public function createRequestExecutor()
    {
        foreach ($this->configuration->getPreferredEngines() as $engine) {
            switch ($engine) {
                case 'libevent':
                    if (extension_loaded('libevent')) {
                        return new LibEventRequestExecutor(new LibEventStageFactory(), $this->configuration);
                    }
                    break;
                case 'native':
                    return new NativeRequestExecutor(
                        new PipelineFactory(
                            new NativeStageFactory()
                        ),
                        $this->configuration
                    );
            }
        }

        throw new \InvalidArgumentException(
            'Provided configuration does not contain any supported RequestExecutor engine.'
        );
    }
}
