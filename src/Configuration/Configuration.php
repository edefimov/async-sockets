<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
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
    private $preferredEngines;

    /**
     * Default stream context for sockets
     *
     * @var StreamContext
     */
    private $streamContext;

    /**
     * Minimum receive speed
     *
     * @var int|null
     */
    private $minReceiveSpeed;

    /**
     * Minimum receive speed duration
     *
     * @var int|null
     */
    private $minReceiveSpeedDuration;

    /**
     * Minimum send speed
     *
     * @var int|null
     */
    private $minSendSpeed;

    /**
     * Minimum send speed duration
     *
     * @var int|null
     */
    private $minSendSpeedDuration;

    /**
     * Configuration constructor.
     *
     * @param array $options {
     *      Array with options
     *
     *      @var double $connectTimeout Connection timeout, in seconds
     *      @var double $ioTimeout Timeout on I/O operations, in seconds
     *      @var string[] $preferredEngines Array with aliases of preferred RequestExecutor engines
     *      @var array|resource|null $streamContext Any valid stream context created by stream_context_create function
     *              or null or array with options. Will be passed to the socket open method. If array value is used,
     *              then it should contain two nested keys: "options" and "params", which will be passed to
     *              stream_context_create parameters respectively.
     *      @var int $minReceiveSpeed Minimum speed required for receiving transfer in bytes per second
     *      @var int $minReceiveSpeedDuration Duration of transfer speed is below than minimum after which request
     *              should be aborted, in seconds
     *      @var int $minSendSpeed Minimum speed required for sending transfer in bytes per second
     *      @var int $minSendSpeedDuration Duration of transfer speed is below than minimum after which request
     *              should be aborted, in seconds
     * }
     */
    public function __construct(array $options = [])
    {
        $options += $this->getDefaultOptions();

        $this->connectTimeout   = (double) $options[ 'connectTimeout' ];
        $this->ioTimeout        = (double) $options[ 'ioTimeout' ];
        $this->preferredEngines = (array) $options[ 'preferredEngines' ];

        $this->streamContext = new StreamContext($options[ 'streamContext' ]);
        foreach (['minReceiveSpeed', 'minReceiveSpeedDuration', 'minSendSpeed', 'minSendSpeedDuration'] as $k) {
            $this->{$k} = $options[$k] !== null ? (int) $options[$k] : null;
        }
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
     * Return default socket stream context
     *
     * @return resource
     */
    public function getStreamContext()
    {
        return $this->streamContext->getResource();
    }

    /**
     * Return MinReceiveSpeed
     *
     * @return int|null
     */
    public function getMinReceiveSpeed()
    {
        return $this->minReceiveSpeed;
    }

    /**
     * Return MinReceiveSpeedDuration
     *
     * @return int|null
     */
    public function getMinReceiveSpeedDuration()
    {
        return $this->minReceiveSpeedDuration;
    }

    /**
     * Return MinSendSpeed
     *
     * @return int|null
     */
    public function getMinSendSpeed()
    {
        return $this->minSendSpeed;
    }

    /**
     * Return MinSendSpeedDuration
     *
     * @return int|null
     */
    public function getMinSendSpeedDuration()
    {
        return $this->minSendSpeedDuration;
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

        $result[ 'connectTimeout' ]          = $socketTimeout;
        $result[ 'ioTimeout' ]               = $socketTimeout;
        $result[ 'preferredEngines' ]        = [ 'libevent', 'native' ];
        $result[ 'streamContext' ]           = null;
        $result[ 'minReceiveSpeed' ]         = null;
        $result[ 'minReceiveSpeedDuration' ] = null;
        $result[ 'minSendSpeed' ]            = null;
        $result[ 'minSendSpeedDuration' ]    = null;

        return $result;
    }
}
