<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo;

use AsyncSockets\Exception\RawSocketException;
use AsyncSockets\Socket\AsyncSocketFactory;

/**
 * Class SimpleClient
 */
final class SimpleClient
{
    /**
     * Main
     *
     * @return void
     * @throws \Exception
     */
    public function main()
    {
        try {
            $factory = new AsyncSocketFactory();

            $client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
            $client->open('tls://github.com:443');
            $client->write("GET / HTTP/1.1\nHost: github.com\n\n");
            $response = $client->read();
            $client->close();

            echo $response;
        } catch (RawSocketException $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
