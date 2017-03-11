<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\RequestExecutor;

/**
 * Class ExecutionContext
 */
class ExecutionContext implements \ArrayAccess
{
    /**
     * Data items
     *
     * @var array
     */
    private $items;

    /**
     * Nested contexts
     *
     * @var ExecutionContext[]
     */
    private $namespaces;

    /**
     * ExecutionContext constructor.
     *
     * @param array $items Initial data for context
     */
    public function __construct(array $items = [])
    {
        $this->items      = $items;
        $this->namespaces = [];
    }

    /**
     * Return nested isolated execution context
     *
     * @param string $namespace Namespace name
     *
     * @return ExecutionContext
     */
    public function inNamespace($namespace)
    {
        if (!isset($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = new static([]);
        }

        return $this->namespaces[$namespace];
    }

    /**
     * Clears data inside the context
     *
     * @return void
     */
    public function clear()
    {
        $this->items = [];
    }

    /**
     * Set a value
     *
     * @param string|int $key   Key
     * @param mixed      $value A value
     *
     * @return void
     */
    public function set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    /**
     * Return a value stored under the key
     *
     * @param string|int $key A key
     * @param mixed      $default Default value to return if key does not exist
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->items[$key] : $default;
    }

    /**
     * Return true if context has value for a given key
     *
     * @param string|int $key A key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Removes a value under given key
     *
     * @param string|int $key A key
     *
     * @return void
     */
    public function remove($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return isset($this->items[$offset]) || array_key_exists($offset, $this->items);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->items[$offset] : null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }
}
