<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Socket;

use AsyncSockets\Configuration\Configuration;
use AsyncSockets\RequestExecutor\LibEventRequestExecutor;
use AsyncSockets\RequestExecutor\NativeRequestExecutor;
use AsyncSockets\RequestExecutor\Pipeline\BaseStageFactory;
use AsyncSockets\RequestExecutor\Pipeline\PipelineFactory;
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
     * Boolean flag whether it is persistent socket, applicable only for SOCKET_CLIENT type
     */
    const SOCKET_OPTION_IS_PERSISTENT = 'soIsPersistent';

    /**
     * Key in php storage to allow multiple persistent connections to the same host [a-zA-Z0-9_-]
     */
    const SOCKET_OPTION_PERSISTENT_KEY = 'soPersistentKey';

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
     * @param string      $type Socket type to create, one of SOCKET_* consts
     * @param array       $options  $flags Flags with socket settings, see SOCKET_OPTION_* consts
     *
     * @return SocketInterface
     * @api
     */
    public function createSocket($type = self::SOCKET_CLIENT, array $options = [])
    {
        switch ($type) {
            case self::SOCKET_CLIENT:
                $isPersistent  = isset($options[ self::SOCKET_OPTION_IS_PERSISTENT ]) &&
                                 $options[ self::SOCKET_OPTION_IS_PERSISTENT ];
                $persistentKey = isset($options[ self::SOCKET_OPTION_PERSISTENT_KEY ]) ?
                    $options[ self::SOCKET_OPTION_PERSISTENT_KEY ] :
                    null;

                return $isPersistent ?
                    new PersistentClientSocket($persistentKey) :
                    new ClientSocket();
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
                        return new LibEventRequestExecutor(new BaseStageFactory(), $this->configuration);
                    }
                    break;
                case 'native':
                    return new NativeRequestExecutor(
                        new PipelineFactory(
                            new BaseStageFactory()
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
