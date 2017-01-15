<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\PhpUnit;

use Symfony\Component\Yaml\Parser;

/**
 * Class AbstractTestCase
 */
abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Make some preparations for test method
     *
     * @param string $testMethod Test method from this class
     *
     * @return void
     */
    protected function prepareFor($testMethod)
    {
        $method = 'prepareFor' . ucfirst($testMethod);
        if (method_exists($this, $method)) {
            $args = func_get_args();
            array_shift($args);
            call_user_func_array([$this, $method], $args);
        }
    }

    /**
     * Convert dataProvider return value from associative array to list of function arguments
     *
     * @param string  $method Target method name in class
     * @param array[] $arguments List of arrays with key-value pairs. Key name must be an argument in target method
     *
     * @return array[] Data according to phpunit dataProvider specification
     * @throws \LogicException
     */
    protected function associativeArrayToArguments($method, array $arguments)
    {
        $ref    = new \ReflectionMethod(get_class($this), $method);
        $result = [];

        foreach ($arguments as $line => $argumentLine) {
            $list = [];
            foreach ($ref->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (!array_key_exists($name, $argumentLine)) {
                    throw new \LogicException(
                        "Parameter {$name} is undefined on data line {$line} for method {$method} in " .
                        get_class($this)
                    );
                }

                $list[$parameter->getPosition()] = $argumentLine[$name];
            }

            $result[] = $list;
        }

        return $result;
    }

    /**
     * Read data for dataProvider from Yaml
     *
     * @param string $dir Path to current class
     * @param string $className This class name
     * @param string $section Root section with data
     * @param string $targetMethod Target test method name
     *
     * @return array
     */
    protected function dataProviderFromYaml($dir, $className, $section, $targetMethod)
    {
        $substr    = substr($className, strrpos($className, '\\') + 1);
        $fileName  = $dir . '/data/' . $substr . '.yml';
        $parser    = new Parser();
        $yaml      = $parser->parse(file_get_contents($fileName), true, true, false);
        if (!$yaml) {
            throw new \LogicException('Failed to parse ' . $fileName);
        }

        if (!isset($yaml[$section])) {
            throw new \LogicException("Section {$section} does not exist in {$fileName}");
        }

        return $this->associativeArrayToArguments($targetMethod, $yaml[$section]);
    }

    /**
     * boolDataProvider
     *
     * @return array
     */
    public function boolDataProvider()
    {
        return [ [false], [true] ];
    }
}
