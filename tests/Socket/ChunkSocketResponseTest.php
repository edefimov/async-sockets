<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\Socket;

use AsyncSockets\Socket\ChunkSocketResponse;

/**
 * Class ChunkSocketResponseTest
 */
class ChunkSocketResponseTest extends SocketResponseTest
{
    /** {@inheritdoc} */
    protected function createResponse($data)
    {
        return new ChunkSocketResponse($data);
    }

    /**
     * testCreatingChunk
     *
     * @return void
     */
    public function testCreatingChunk()
    {
        $rounds = 10;
        $data   = '';
        for ($i = 0; $i < $rounds; $i++) {
            $data .= md5(microtime(true) * ($i + mt_rand(1, 10)));
        }

        $response = null;
        for ($i = 0; $i < strlen($data); $i++) {
            $response = new ChunkSocketResponse($data[$i], $response);
            self::assertEquals($data[$i], $response->getChunkData(), 'Incorrect chunk data returned');
        }

        self::assertSame($data, $response->getData(), 'Construction of response is failed');
        self::assertSame($data, (string) $response, 'String casting is failed');
    }
}
