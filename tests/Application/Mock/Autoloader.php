<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\Application\Mock;

/**
 * Class Autoloader
 */
class Autoloader extends AbstractPhpMocker
{
    /**
     * Class map
     *
     * @var array
     */
    private $classMap;

    /**
     * Map of intercepting namespaces
     *
     * @var array
     */
    private $interceptors;

    /**
     * Autoloader constructor.
     *
     * @param array  $classMap Class map to autoload
     * @param array  $interceptors Array of interceptors
     */
    public function __construct(array $classMap, array $interceptors)
    {
        $this->classMap     = $classMap;
        $this->interceptors = $interceptors;
    }

    /**
     * Register loader
     *
     * @return void
     */
    public function register()
    {
        spl_autoload_register([$this, 'onAutoload'], true, true);
    }

    /**
     * Autoload function
     *
     * @param string $className Class name to autoload
     *
     * @return void
     */
    public function onAutoload($className)
    {
        if (isset($this->classMap[$className])) {
            include $this->classMap[$className];
            return;
        }

        $namespace = $this->getClassNameSpace($className);
        if (isset($this->interceptors[$namespace])) {
            include $this->interceptors[$namespace];
            unset($this->interceptors[$namespace]);
        }
    }
}
