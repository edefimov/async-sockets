<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2016, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace AsyncSockets\Operation;

/**
 * Class NullOperation. Special no-value operation. Do not use in your code!
 */
class NullOperation implements OperationInterface
{
    /**
     * Return single instance of operation
     *
     * @return NullOperation
     */
    public static function getInstance()
    {
        static $instance;
        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * @inheritDoc
     */
    public function getType()
    {
        return self::OPERATION_READ;
    }
}
