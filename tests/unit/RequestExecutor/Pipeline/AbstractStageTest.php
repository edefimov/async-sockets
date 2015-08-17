<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\AsyncSockets\RequestExecutor\Pipeline;

use AsyncSockets\RequestExecutor\Metadata\OperationMetadata;
use AsyncSockets\RequestExecutor\Pipeline\EventCaller;
use AsyncSockets\RequestExecutor\Pipeline\PipelineStageInterface;
use AsyncSockets\RequestExecutor\RequestExecutorInterface;
use Tests\AsyncSockets\PhpUnit\AbstractTestCase;

/**
 * Class AbstractStageTest
 */
abstract class AbstractStageTest extends AbstractTestCase
{
    use MetadataStructureAwareTrait;

    /**
     * Executor
     *
     * @var RequestExecutorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $executor;

    /**
     * Event caller
     *
     * @var EventCaller|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventCaller;

    /**
     * Test object
     *
     * @var PipelineStageInterface
     */
    protected $stage;

    /**
     * Metadata test array
     *
     * @var array
     */
    protected $metadata;

    /**
     * Create test object
     *
     * @return PipelineStageInterface
     */
    abstract protected function createStage();

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->metadata = $this->getMetadataStructure();
        $bag            = $this->getMockBuilder('AsyncSockets\RequestExecutor\SocketBagInterface')
                               ->setMethods([ 'getSocketMetaData' ])
                               ->getMockForAbstractClass();
        $this->executor = $this->getMockBuilder('AsyncSockets\RequestExecutor\RequestExecutorInterface')
                               ->setMethods(['socketBag'])
                               ->getMockForAbstractClass();
        $bag->expects(self::any())->method('getSocketMetaData')->willReturn($this->metadata);
        $this->executor->expects(self::any())->method('socketBag')->willReturn($bag);

        $this->eventCaller = $this->getMock(
            'AsyncSockets\RequestExecutor\Pipeline\EventCaller',
            [ 'callExceptionSubscribers', 'callSocketSubscribers' ],
            [ $this->executor ]
        );
        $this->stage       = $this->createStage();
    }

    /**
     * setupEventCallerForSocketException
     *
     * @param \PHPUnit_Framework_MockObject_MockObject $mock Mock object to setup
     *
     * @return void
     */
    protected function setupEventCallerForSocketException(\PHPUnit_Framework_MockObject_MockObject $mock)
    {
        $mock->expects(self::any())->method('callExceptionSubscribers');
    }

    /**
     * createOperationMetadata
     *
     * @return OperationMetadata|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createOperationMetadata()
    {
        $operationMetadata = $this->getMock(
            'AsyncSockets\RequestExecutor\Metadata\OperationMetadata',
            [
                'initialize',
                'getMetadata',
                'setMetadata',
                'setRunning',
                'getSocket',
                'isRunning',
                'getOperation',
                'setOperation',
            ],
            [ ],
            '',
            false
        );

        return $operationMetadata;
    }
}
