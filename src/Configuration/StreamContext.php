<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\Configuration;

/**
 * Class StreamContext
 */
class StreamContext
{
    /**
     * Stream context resource
     *
     * @var resource
     */
    private $resource;

    /**
     * StreamContext constructor.
     *
     * @param array|resource|\Traversable|null $settings Context options
     */
    public function __construct($settings)
    {
        $this->resource = $this->createResource($settings);
    }

    /**
     * Return stream context resource
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Creates resource from given settings
     *
     * @param array|resource|\Traversable|null $settings Context settings
     *
     * @return resource
     */
    protected function createResource($settings)
    {
        if ($settings instanceof \Traversable) {
            $settings = iterator_to_array($settings);
        }

        if (is_resource($settings)) {
            return $settings;
        } elseif (is_array($settings)) {
            return stream_context_create(
                isset($settings[ 'options' ]) ? $settings[ 'options' ] : [ ],
                isset($settings[ 'params' ]) ? $settings[ 'params' ] : [ ]
            );
        } elseif ($settings === null) {
            return stream_context_get_default();
        } else {
            throw new \InvalidArgumentException(
                sprintf(
                    'Can not create stream context for variable type %s',
                    is_object($settings) ? get_class($settings) : gettype($settings)
                )
            );
        }
    }
}
