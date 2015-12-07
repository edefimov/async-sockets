<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\RequestExecutor\Specification;

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\Operation\ReadOperation;
use AsyncSockets\Socket\UdpClientSocket;

/**
 * Class ConnectionLessSocketSpecification
 */
class ConnectionLessSocketSpecification implements SpecificationInterface
{
    /** {@inheritdoc} */
    public function isSatisfiedBy(OperationMetadata $operationMetadata)
    {
        return $operationMetadata->getSocket() instanceof UdpClientSocket &&
               $operationMetadata->getOperation() instanceof ReadOperation;
    }
}
