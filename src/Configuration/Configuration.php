<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Configuration;

/**
 * Class Configuration
 */
class Configuration
{
    /**
     * Connection timeout
     *
     * @var double
     */
    private $connectTimeout;

    /**
     * I/O operations timeout
     *
     * @var double
     */
    private $ioTimeout;

    /**
     * Aliases of preferred RequestExecutor engines
     *
     * @var string[]
     */
    private $preferredEngines = [];

    /**
     * Configuration constructor.
     *
     * @param array $options {
     *      Array with options
     *
     *      @var double $connectTimeout Connection timeout
     *      @var double $ioTimeout Timeout on I/O operations
     *      @var string[] $preferredEngines Array with aliases of preferred RequestExecutor engines
     * }
     */
    public function __construct(array $options = [])
    {
        $options += $this->getDefaultOptions();

        $this->connectTimeout   = (double) $options[ 'connectTimeout' ];
        $this->ioTimeout        = (double) $options[ 'ioTimeout' ];
        $this->preferredEngines = (array) $options[ 'preferredEngines' ];
    }

    /**
     * Return ConnectTimeout
     *
     * @return float
     */
    public function getConnectTimeout()
    {
        return $this->connectTimeout;
    }

    /**
     * Return IoTimeout
     *
     * @return float
     */
    public function getIoTimeout()
    {
        return $this->ioTimeout;
    }

    /**
     * Return PreferredEngines
     *
     * @return string[]
     */
    public function getPreferredEngines()
    {
        return $this->preferredEngines;
    }

    /**
     * Key-value array with default values for options
     *
     * @return array
     */
    private function getDefaultOptions()
    {
        $result        = [];
        $socketTimeout = (double) ini_get('default_socket_timeout');

        $result[ 'connectTimeout' ]   = $socketTimeout;
        $result[ 'ioTimeout' ]        = $socketTimeout;
        $result[ 'preferredEngines' ] = [ 'libevent', 'native' ];

        return $result;
    }
}
