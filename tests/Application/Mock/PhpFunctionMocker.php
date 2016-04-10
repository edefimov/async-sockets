<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\Application\Mock;

use Composer\Autoload\ClassLoader;

/**
 * Class PhpFunctionMocker
 */
class PhpFunctionMocker extends AbstractPhpMocker
{
    /**
     * Array of mocked functions
     *
     * @var PhpFunctionMocker[]
     */
    private static $mocks = [];

    /**
     * Return mock function for php given one
     *
     * @param string $funcName Php function name to mock
     *
     * @return PhpFunctionMocker
     */
    public static function getPhpFunctionMocker($funcName)
    {
        if (!isset(self::$mocks[$funcName])) {
            $mockerClass = get_class() . '_' . $funcName;
            $object      = new $mockerClass($funcName);
            $defName     = self::getFunctionDefinitionName($funcName);

            self::$mocks[$defName] = $object;
        }

        return self::$mocks[$funcName];
    }

    /**
     * Stub function
     *
     * @var callable
     */
    private $callable;

    /**
     * Mocked function name
     *
     * @var callable
     */
    private $functionName;

    /**
     * Constructor
     *
     * @param callable $functionName PHP Function name
     */
    public function __construct($functionName)
    {
        $this->functionName = $functionName;
    }

    /**
     * Set new callable for php function
     *
     * @param callable $function New function
     *
     * @return void
     */
    public function setCallable($function)
    {
        $this->callable = $function;
    }

    /**
     * Restore native php handler for this function
     *
     * @return void
     */
    public function restoreNativeHandler()
    {
        $this->callable = null;
    }

    /**
     * Return callable to execute
     *
     * @return callable
     */
    protected function getTargetCallable()
    {
        $callable = '\\' . $this->functionName;
        if (is_callable($this->callable)) {
            $callable = $this->callable;
        }

        return $callable;
    }
}
