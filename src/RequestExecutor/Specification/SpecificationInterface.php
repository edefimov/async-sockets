<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Specification;

use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;

/**
 * Interface SpecificationInterface
 */
interface SpecificationInterface
{
    /**
     * Check whether given socket is satisfied by this specification
     *
     * @param RequestDescriptor $requestDescriptor Operation object
     *
     * @return bool
     */
    public function isSatisfiedBy(RequestDescriptor $requestDescriptor);
}
