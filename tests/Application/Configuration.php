<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\Application;

use Symfony\Component\Yaml\Yaml;

/**
 * Class Configuration
 */
class Configuration
{
    /**
     * Library root
     *
     * @var string
     */
    private $libraryRoot;

    /**
     * Key-value configuration data
     *
     * @var array
     */
    private $config;

    /**
     * Configuration constructor.
     *
     * @param string $libraryRoot Root directory
     * @param string $fileName Path to config.yaml relative to library root
     */
    public function __construct($libraryRoot, $fileName)
    {
        $this->libraryRoot = realpath($libraryRoot);
        $this->config      = Yaml::parse(file_get_contents($this->libraryRoot . '/' . $fileName));
        if (!$this->config) {
            throw new \RuntimeException("Configuration file {$fileName} is invalid");
        }

        $this->config = (isset($this->config['tests']) ? $this->config['tests'] : []) +
            [
                'cache_dir' => 'build/cache',
                'source_dir' => 'src'
            ];

        if (isset($this->config['cache_dir'][0]) && $this->config['cache_dir'][0] !== '/') {
            $this->config['cache_dir'] = $this->libraryRoot . '/' . $this->config['cache_dir'];
        }
    }

    /**
     * Return absolute path to cache directory
     *
     * @return string
     */
    public function cacheDir()
    {
        return $this->config['cache_dir'];
    }
}
