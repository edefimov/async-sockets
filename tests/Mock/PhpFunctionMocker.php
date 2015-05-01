<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\Mock;

use Composer\Autoload\ClassLoader;

/**
 * Class PhpFunctionMocker
 *
 * @SuppressWarnings("EvalExpression")
 */
class PhpFunctionMocker
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
            self::$mocks[$funcName] = $object;
        }

        return self::$mocks[$funcName];
    }

    /**
     * Main loader for this mocker class
     *
     * @param ClassLoader|null $classLoader Composer class loader
     *
     * @return void
     */
    public static function bootstrap(ClassLoader $classLoader = null)
    {
        $nativeFunctions     = array_flip(get_defined_functions()['internal']);
        $getDefinedFunctions = function ($class) use ($classLoader, &$nativeFunctions) {
            $result = $nativeFunctions;
            if ($classLoader) {
                $file = $classLoader->findFile($class);
                if ($file) {
                    $tokens       = token_get_all(
                        file_get_contents($file)
                    );
                    $functions    = [ ];
                    for ($i = 0; $i < count($tokens); $i++) {
                        $token = $tokens[$i];
                        $isPossibleFunction = is_array($token) &&
                                              T_STRING === $token[0] &&
                                              isset($nativeFunctions[$token[1]]);
                        if ($isPossibleFunction) {
                            $functions[$token[1]] = $token[1];
                        }
                    }

                    $result = $functions;
                }
            }

            return array_keys($result);
        };


        \spl_autoload_register(function ($className) use ($getDefinedFunctions) {
            $namespace = self::getClassNameSpace($className);
            if (!$namespace) {
                return;
            }

            foreach ($getDefinedFunctions($className) as $function) {
                self::defineFunction($function, $namespace);
            }
        }, true, true);
    }

    /**
     * Define mock function
     *
     * @param string $funcName Function name
     * @param string $namespace Target namespace
     *
     * @return void
     */
    private static function defineFunction($funcName, $namespace)
    {
        static $phpNamespace;

        $myReference = get_class();
        if ($phpNamespace === null) {
            $phpNamespace = 'X' . md5($myReference);
        }

        if (function_exists("{$namespace}\\{$funcName}")) {
            return;
        }

        $myNamespace = self::getClassNameSpace($myReference);
        $function    = new \ReflectionFunction('\\' . $funcName);
        if (method_exists($function, 'isVariadic') && $function->isVariadic()) {
            return;
        }

        $argList     = self::getFunctionArgumentList($function);
        $callList    = self::getFunctionCallList($function->getParameters());

        $separatedArgList  = $argList === '' ? '' : ', ' .  $argList;
        $separatedCallList = $callList === '' ? '' : ', ' .  $callList;

        if (!class_exists("{$myReference}_{$funcName}", false)) {
            $invocationCode = self::getInvocationCode($function);
            eval(<<<MAGIC
namespace {$myNamespace} {
    class PhpFunctionMocker_{$funcName} extends PhpFunctionMocker
    {
        public static function invokeMethod(\$callable {$separatedArgList})
        {
            if (\$callable instanceof \\Closure || \method_exists(\$callable, '__invoke')) {
                return \$callable({$callList});
            }

            {$invocationCode}
        }

        public function __invoke({$argList})
        {
            \$callable = \$this->getTargetCallable();
            return self::invokeMethod(\$callable {$separatedCallList});
        }
    }
}
MAGIC
            );
        }

        if (!function_exists("{$phpNamespace}\\{$funcName}")) {
            eval(<<<MAGIC
namespace {$phpNamespace} {
   function {$funcName} ({$argList})
   {
       \$closure = function() {
                return isset(\\{$myReference}::\$mocks['{$funcName}']) ?
                    \\{$myReference}::\$mocks['{$funcName}'] : null;
       };

       \$closure = \$closure->bindTo(null, '\\{$myReference}');
       \$mocker  = \$closure();
       return \$mocker !== null ? \$mocker({$callList}) :
                                  \\{$myNamespace}\\PhpFunctionMocker_{$funcName}::invokeMethod(
                                      '\\{$funcName}'
                                      {$separatedCallList}
                                  );
   }
}
MAGIC
            );
        }

        eval(<<<MAGIC
namespace {$namespace} {
   function {$funcName} ({$argList})
   {
       return \\{$phpNamespace}\\{$funcName}({$callList});
   }
}
MAGIC
        );
    }

    /**
     * Return argument list definition for function
     *
     * @param \ReflectionFunction $function Function object
     *
     * @return string
     */
    private static function getFunctionArgumentList(\ReflectionFunction $function)
    {
        $result = [];
        foreach ($function->getParameters() as $parameter) {
            $argument   = '$' . $parameter->getName();
            if ($parameter->isPassedByReference()) {
                $argument = '&' . $argument;
            }

            if ($parameter->isArray()) {
                $argument = 'array ' . $argument;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $argument .= '=' . $parameter->getDefaultValue();
            } elseif ($parameter->isOptional()) {
                $argument .= ' = null';
            }

            $result[$parameter->getPosition()] = $argument;
        }

        \ksort($result);
        return \implode(',', $result);
    }

    /**
     * Return string with php code for for function call list
     *
     * @param \ReflectionParameter[] $parameters Function object
     *
     * @return string
     */
    private static function getFunctionCallList(array $parameters)
    {
        $result = [];
        foreach ($parameters as $parameter) {
            $argument   = '$' . $parameter->getName();
            $result[$parameter->getPosition()] = $argument;
        }

        \ksort($result);
        return \implode(',', $result);
    }

    /**
     * Generate invocation code
     *
     * @param \ReflectionFunction $function Function object
     *
     * @return string
     */
    private static function getInvocationCode(\ReflectionFunction $function)
    {
        $code = [];
        $name = $function->getName();

        /** @var \ReflectionParameter[] $parameters */
        $parameters = [];
        foreach ($function->getParameters() as $parameter) {
            $parameters[$parameter->getPosition()] = $parameter;
        }

        if (!$parameters) {
            return "return \\{$name}();";
        }

        $orderedParameters = $parameters;

        \ksort($orderedParameters);
        \krsort($parameters);


        foreach ($parameters as $position => $parameter) {
            $callList = self::getFunctionCallList(array_slice($orderedParameters, 0, $position + 1));
            if ($parameter->isOptional()) {
                $code[$parameter->getPosition()] = <<<CODE
if (\${$parameter->getName()} !== null) {
    return \\{$name}({$callList});
}
CODE;
            } else {
                $code[] = "return \\{$name}({$callList});";
                break;
            }
        }

        return implode("\n", $code);
    }

    /**
     * Return namespace from class name
     *
     * @param string $className Class name
     *
     * @return string
     */
    private static function getClassNameSpace($className)
    {
        return substr($className, 0, strrpos($className, '\\'));
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
     * @param \Closure $function New function
     *
     * @return void
     */
    public function setCallable(\Closure $function)
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
