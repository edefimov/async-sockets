<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\RequestExecutorInterface;

/**
 * Trait MetadataStructureAwareTrait
 */
trait MetadataStructureAwareTrait
{
    /**
     * getMetadataStructure
     *
     * @return array
     */
    protected function getMetadataStructure()
    {
        $result = [];
        $ref      = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutorInterface');
        foreach ($ref->getConstants() as $name => $value) {
            if (strpos($name, 'META_') === 0) {
                $result[$value] = null;
            }
        }

        $result[RequestExecutorInterface::META_USER_CONTEXT] = sha1(microtime(true));

        return $result;
    }
}
