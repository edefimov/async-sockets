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

use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\RequestExecutor\Metadata\RequestDescriptor;
use AsyncSockets\Socket\UdpClientSocket;

/**
 * Class ConnectionLessSocketSpecification
 */
class ConnectionLessSocketSpecification implements SpecificationInterface
{
    /** {@inheritdoc} */
    public function isSatisfiedBy(RequestDescriptor $requestDescriptor)
    {
        return $requestDescriptor->getSocket() instanceof UdpClientSocket &&
               $requestDescriptor->getOperation() instanceof ReadOperation;
    }
}
