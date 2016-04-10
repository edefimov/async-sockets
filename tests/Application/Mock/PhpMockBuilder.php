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

/**
 * Class PhpMockBuilder
 */
class PhpMockBuilder extends AbstractPhpMocker
{
    /**
     * Cache directory
     *
     * @var string
     */
    private $cacheDir;

    /**
     * Namespace with php handlers
     *
     * @var string
     */
    private $phpNamespace;

    /**
     * Code with interceptors separated by namespace and function name
     *
     * @var string[][]
     */
    private $interceptorCode;

    /**
     * Code with target handlers, indexed by class name
     *
     * @var string[]
     */
    private $handlerCode;

    /**
     * PhpMockBuilder constructor.
     *
     * @param string $cacheDir Cache directory
     */
    public function __construct($cacheDir)
    {
        $this->cacheDir        = $cacheDir . '/phpmocker';
        $this->phpNamespace    = 'X' . md5(get_class($this));
        $this->interceptorCode = [ ];
        $this->handlerCode     = [ ];
    }

    /**
     * Build mocker for given file name and save into cache dir
     *
     * @param string $fileName Source file
     *
     * @return void
     */
    public function build($fileName)
    {
        $tokens = token_get_all(
            file_get_contents($fileName)
        );

        $namespace = $this->extractNamespaceFromTokens($tokens);
        if (!$namespace) {
            return;
        }
        $functions = $this->extractFunctionsFromTokens($tokens);


        foreach ($functions as $function) {
            $this->buildFunction($function, $namespace);
        }
    }

    /**
     * Flush collected data
     *
     * @return void
     */
    public function flush()
    {
        $phpHandlersPath = $this->cacheDir . '/handlers';
        if (!is_dir($phpHandlersPath)) {
            mkdir($phpHandlersPath, 0755, true);
        }

        $classMap        = [];
        $myNameSpace     = $this->getClassNameSpace(get_class($this));
        foreach ($this->handlerCode as $className => $source) {
            $fileName = $phpHandlersPath . '/' . $className . '.php';
            $classMap[] = "'{$myNameSpace}\\{$className}' => '{$fileName}',";
            file_put_contents($fileName, $source);
        }

        $interceptorsPath = $this->cacheDir . '/interceptor';
        if (!is_dir($interceptorsPath)) {
            mkdir($interceptorsPath, 0755, true);
        }

        $interceptors = [];
        foreach ($this->interceptorCode as $namespace => $sources) {
            $fileName = $interceptorsPath . '/' . str_replace('\\', '.', $namespace) . '.php';

            $contents = "<?php\n";
            if ($namespace !== $this->phpNamespace) {
                $interceptors[] = "'{$namespace}' => '{$fileName}',";
            } else {
                $contents .= "namespace {$this->phpNamespace};\n";
            }

            file_put_contents(
                $fileName,
                $contents . implode("\n\n", $sources)
            );
        }

        $classMap       = implode("\n", $classMap);
        $interceptors   = implode("\n", $interceptors);
        $autloaderClass = 'PhpMockerLoader' . md5(microtime(true));
        file_put_contents(
            $this->cacheDir . '/PhpMockerLoader.php',
            <<<AUTLOADER
<?php

class {$autloaderClass} extends \\Tests\\Application\\Mock\\Autoloader {
    private static \$loader;

    /** @return \\Tests\\Application\\Mock\\Autoloader */
    public static function getLoader()
    {
        if (self::\$loader) {
            return self::\$loader;
        }

        self::\$loader = new static(
            [{$classMap}],
            [{$interceptors}]
        );

        self::\$loader->register();

        return self::\$loader;
    }
}

AUTLOADER
        );

        file_put_contents(
            $this->cacheDir . '/autoload.php',
            <<<AUTOLOAD
<?php

require_once __DIR__ . '/PhpMockerLoader.php';
require_once __DIR__ . '/interceptor/{$this->phpNamespace}.php';

return {$autloaderClass}::getLoader();

AUTOLOAD
        );
    }

    /**
     * Return functions from token list
     *
     * @param array $tokens PHP tokens
     *
     * @return string[] List of defined functions in file
     */
    private function extractFunctionsFromTokens(array $tokens)
    {
        $nativeFunctions = array_flip(get_defined_functions()[ 'internal' ]);

        $functions = [ ];
        for ($i = 0; $i < count($tokens); $i++) {
            $token              = $tokens[ $i ];
            $isPossibleFunction = is_array($token) &&
                                  T_STRING === $token[ 0 ] &&
                                  isset($nativeFunctions[ $token[ 1 ] ]);
            if ($isPossibleFunction) {
                $functions[ $token[ 1 ] ] = $token[ 1 ];
            }
        }

        return array_keys($functions);
    }

    /**
     * Return defined namespace in list of tokens
     *
     * @param array $tokens PHP tokens
     *
     * @return string
     */
    private function extractNamespaceFromTokens(array $tokens)
    {
        $namespace = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token       = $tokens[ $i ];
            $isNamespace = is_array($token) &&
                           T_NAMESPACE === $token[ 0 ];
            if (!$isNamespace) {
                continue;
            }

            $i += 1;
            while ($i < count($tokens)) {
                $token = $tokens[ $i ];
                $isNamespace = is_array($token) &&
                               T_STRING === $token[ 0 ];
                if ($isNamespace) {
                    $namespace[] = $token[1];
                }

                if (!is_array($token) && $token === ';') {
                    break 2;
                }
                $i += 1;
            }
        }

        return implode('\\', $namespace);
    }

    /**
     * Build definition file for function
     *
     * @param string $funcName Function name
     * @param string $namespace Source namespace
     *
     * @return void
     */
    private function buildFunction($funcName, $namespace)
    {
        $myReference = get_class();

        $myNamespace = $this->getClassNameSpace($myReference);
        $function    = new \ReflectionFunction('\\' . $funcName);
        if ($this->isVariadicFunction($function)) {
            return;
        }

        $functionDefName = self::getFunctionDefinitionName($funcName);

        $argList  = $this->getFunctionArgumentList($function);
        $callList = $this->getFunctionCallList($function->getParameters());

        $separatedArgList  = $argList === '' ? '' : ', ' .  $argList;
        $separatedCallList = $callList === '' ? '' : ', ' .  $callList;

        $invocationCode = $this->getInvocationCode($function);
        $handlerClass   = "PhpFunctionMocker_{$functionDefName}";
        $sourceCode = <<<MAGIC
<?php

namespace {$myNamespace} {
    class {$handlerClass} extends PhpFunctionMocker
    {
        public static function invokeMethod(\$callable, \$argCount {$separatedArgList})
        {
            if (\$callable instanceof \\Closure || \method_exists(\$callable, '__invoke')) {
                return \$callable({$callList});
            }

            if (is_array(\$callable)) {
                if (count(\$callable) == 2) {
                     \$obj = reset(\$callable);
                     \$fn  = end(\$callable);
                     if (is_object(\$obj) && \method_exists(\$obj, \$fn)) {
                         return \$obj->\$fn({$callList});
                     } elseif (is_string(\$obj)) {
                         return \$obj::\$fn({$callList});
                     }
                }

                throw new \InvalidArgumentException('Wrong parameters were passed to {$funcName}');
            }

            {$invocationCode}
        }

        public function __invoke({$argList})
        {
            \$callable = \$this->getTargetCallable();
            return self::invokeMethod(\$callable, \\func_num_args() {$separatedCallList});
        }
    }
}
MAGIC;

        $this->handlerCode[$handlerClass] = $sourceCode;

        $getMockerFunction = 'getMocker' . md5($myReference);
        $this->interceptorCode[$this->phpNamespace][$getMockerFunction] = <<<MAGIC
    function {$getMockerFunction}(\$function)
    {
        static \$ref;
        if (!\$ref) {
            \$ref = new \ReflectionProperty("\\\\Tests\\\\Application\\\\Mock\\\\PhpFunctionMocker", 'mocks');
            \$ref->setAccessible(true);
        }

        \$value = \$ref->getValue();
        return isset(\$value[\$function]) ? \$value[\$function] : null;
    }
MAGIC;

        $handlerArgs = $argList ? '$argCount, ' . $argList : '$argCount';
        $this->interceptorCode[$this->phpNamespace][$functionDefName] = <<<MAGIC

function {$functionDefName} ({$handlerArgs})
{
    \$mocker = {$getMockerFunction}('{$functionDefName}');
    return \$mocker !== null ?
        \$mocker({$callList}) :
        \\{$myNamespace}\\PhpFunctionMocker_{$functionDefName}::invokeMethod(
            '\\{$funcName}',
            \$argCount
            {$separatedCallList}
        );
}
MAGIC;

        $sourceFnNameSpace = $this->getClassNameSpace($funcName);
        $sourceFnNameSpace = $sourceFnNameSpace ? '\\' . $sourceFnNameSpace : '';
        if ($sourceFnNameSpace) {
            $funcName = substr($funcName, strlen($sourceFnNameSpace));
        }

        $this->interceptorCode[$namespace . $sourceFnNameSpace][$funcName] = <<<MAGIC
namespace {$namespace}{$sourceFnNameSpace} {
   function {$funcName} ({$argList})
   {
       return \\{$this->phpNamespace}\\{$functionDefName}(\\func_num_args() {$separatedCallList});
   }
}
MAGIC;
    }

    /**
     * Return argument list definition for function
     *
     * @param \ReflectionFunction $function Function object
     *
     * @return string
     */
    private function getFunctionArgumentList(\ReflectionFunction $function)
    {
        $result = [ ];
        foreach ($function->getParameters() as $parameter) {
            $argument = $this->generateParameterName($parameter);
            if ($parameter->isPassedByReference()) {
                $argument = '&' . $argument;
            }

            if ($parameter->isArray()) {
                $argument = 'array ' . $argument;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $defaultValue = $parameter->getDefaultValue();
                $argument .= '=' . ($this->parameterValueToString($defaultValue) ?: 'null');
            } elseif ($parameter->isOptional()) {
                $argument .= ' = null';
            }

            $result[ $parameter->getPosition() ] = $argument;
        }

        \ksort($result);

        return trim(\implode(',', $result));
    }

    /**
     * Convert parameter value to string
     *
     * @param mixed $value Value
     *
     * @return string
     */
    private function parameterValueToString($value)
    {
        switch (true) {
            case $value === null:
                $result = 'null';
                break;
            case is_bool($value):
                $result = $value ? 'true' : 'false';
                break;
            case is_array($value):
                $result = [];
                foreach ($value as $k => $v) {
                    $result[] = $k . '=>' . $this->parameterValueToString($v);
                }

                $result = 'array(' . implode(',', $result) . ')';
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * Return string with php code for for function call list
     *
     * @param \ReflectionParameter[] $parameters Function object
     *
     * @return string
     */
    private function getFunctionCallList(array $parameters)
    {
        $result = [ ];
        foreach ($parameters as $parameter) {
            $argument                            = $this->generateParameterName($parameter);
            $result[ $parameter->getPosition() ] = $argument;
        }

        \ksort($result);

        return trim(\implode(',', $result));
    }

    /**
     * Check whether given function is variadic
     *
     * @param \ReflectionFunction $function Function to test
     *
     * @return bool
     */
    private function isVariadicFunction(\ReflectionFunction $function)
    {
        if (method_exists($function, 'isVariadic')) {
            return $function->isVariadic();
        }

        // php < 5.6 work-around
        foreach ($function->getParameters() as $parameter) {
            if ($parameter->getName() === '...') {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate invocation code
     *
     * @param \ReflectionFunction $function Function object
     *
     * @return string
     */
    private function getInvocationCode(\ReflectionFunction $function)
    {
        $code = [ ];
        $name = $function->getName();

        /** @var \ReflectionParameter[] $parameters */
        $parameters = [ ];
        foreach ($function->getParameters() as $parameter) {
            $parameters[ $parameter->getPosition() ] = $parameter;
        }

        if (!$parameters) {
            return "return \\{$name}();";
        }

        $orderedParameters = $parameters;

        \ksort($orderedParameters);
        \krsort($parameters);


        foreach ($parameters as $position => $parameter) {
            $callList = $this->getFunctionCallList(array_slice($orderedParameters, 0, $position + 1));
            if ($parameter->isOptional()) {
                $argumentCount = $parameter->getPosition() + 1;
                $code[ ] = <<<CODE
if (\$argCount >= {$argumentCount}) {
    return \\{$name}({$callList});
}
CODE;
                if (!$parameter->getPosition()) {
                    $code[ ] = "return \\{$name}();";
                }
            } else {
                $code[ ] = "return \\{$name}({$callList});";
                break;
            }
        }

        return implode("\n", $code);
    }

    /**
     * Generate unique parameter name
     *
     * @param \ReflectionParameter $parameter Parameter object
     *
     * @return string
     */
    private function generateParameterName(\ReflectionParameter $parameter)
    {
        $name = $parameter->getName();
        if ($name === '...' && version_compare(PHP_VERSION, '5.6', 'lt')) {
            $name = 'x' . dechex(crc32($name));
        }

        return '$' . $name . $parameter->getPosition();
    }
}
