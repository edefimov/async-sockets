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
     * Create test object
     *
     * @return PipelineStageInterface
     */
    abstract protected function createStage();

    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->executor    = $this->getMockForAbstractClass('AsyncSockets\RequestExecutor\RequestExecutorInterface');
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

    /**
     * getMetadataStructure
     *
     * @return array
     */
    protected function getMetadataStructure()
    {
        static $result;

        if (!$result) {
            $result = [];
            $ref      = new \ReflectionClass('AsyncSockets\RequestExecutor\RequestExecutorInterface');
            foreach ($ref->getConstants() as $name => $value) {
                if (strpos($name, 'META_') === 0) {
                    $result[$value] = null;
                }
            }

            $result[RequestExecutorInterface::META_USER_CONTEXT] = sha1(microtime(true));
        }

        return $result;
    }
}
