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
 * Class AbstractPhpMocker
 */
abstract class AbstractPhpMocker
{
    /**
     * Return function definition name in Mock namespace
     *
     * @param string $name Function original name
     *
     * @return string
     */
    protected static function getFunctionDefinitionName($name)
    {
        return preg_replace('#\W+#', '_', $name);
    }

    /**
     * Return namespace from class name
     *
     * @param string $className Class name
     *
     * @return string
     */
    protected function getClassNameSpace($className)
    {
        $pos = strrpos($className, '\\');
        if ($pos === false) {
            return '';
        }

        return substr($className, 0, $pos);
    }
}
