<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Configuration;

use AsyncSockets\Configuration\Configuration;

/**
 * Class ConfigurationTest
 */
class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testCreatingConfiguration
     *
     * @return void
     */
    public function testCreatingConfiguration()
    {
        $options = [
            'connectTimeout'   => (double) mt_rand(1, PHP_INT_MAX),
            'ioTimeout'        => (double) mt_rand(1, PHP_INT_MAX),
            'preferredEngines' => [sha1(microtime(true))]
        ];

        $configuration = new Configuration($options);
        self::assertSame(
            $options[ 'connectTimeout' ],
            $configuration->getConnectTimeout(),
            'Incorrect connect timeout'
        );
        self::assertSame($options[ 'ioTimeout' ], $configuration->getIoTimeout(), 'Incorrect I/O timeout');
        self::assertSame(
            $options[ 'preferredEngines' ],
            $configuration->getPreferredEngines(),
            'Incorrect preferred engines'
        );
    }

    /**
     * testDefaultValues
     *
     * @return void
     */
    public function testDefaultValues()
    {
        $configuration  = new Configuration();
        $defaultTimeout = (double) ini_get('default_socket_timeout');
        self::assertSame(
            $defaultTimeout,
            $configuration->getConnectTimeout(),
            'Incorrect connect timeout'
        );
        self::assertSame($defaultTimeout, $configuration->getIoTimeout(), 'Incorrect I/O timeout');
        self::assertSame(
            ['libevent', 'native'],
            $configuration->getPreferredEngines(),
            'Incorrect preferred engines'
        );
    }
}
