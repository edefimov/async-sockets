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

use AsyncSockets\RequestExecutor\Pipeline\ExcludedOperationsStage;
use AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface;

/**
 * Class ExcludedOperationsStageTest
 */
class ExcludedOperationsStageTest extends AbstractStageTest
{
    /**
     * PipelineStageInterface
     *
     * @var PipelineStageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockStage;

    /** {@inheritdoc} */
    protected function createStage()
    {
        return new ExcludedOperationsStage($this->executor, $this->eventCaller, [$this->mockStage]);
    }

    /**
     * testExcludedOperationsWillBeReturned
     *
     * @return void
     */
    public function testExcludedOperationsWillBeReturned()
    {
        $input = [];
        for ($i = 0; $i < 10; $i++) {
            $input[] = $this->getMock('AsyncSockets\RequestExecutor\Metadata\RequestDescriptor', [], [], '', false);
        }

        $processedByInternal = [$input[0], $input[3], $input[6]];

        $this->mockStage->expects(self::any())
            ->method('processStage')
            ->willReturn($processedByInternal);

        $result = $this->stage->processStage($input);
        foreach ($processedByInternal as $item) {
            self::assertFalse(in_array($item, $result, true), 'Some of processed items were not excluded');
        }
    }

    /** {@inheritdoc} */
    protected function setUp()
    {
        $this->mockStage = $this->getMockForAbstractClass(
            'AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface',
            [],
            '',
            true,
            true,
            true,
            ['processStage']
        );

        parent::setUp();
    }
}
