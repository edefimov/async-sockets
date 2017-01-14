<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\SystemNotificationSample;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Logger
 */
class Logger
{
    /**
     * Output
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * Logger constructor.
     *
     * @param OutputInterface $output Output interface
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Simple log
     *
     * @param string $message Message
     *
     * @return void
     */
    public function log($message)
    {
        $now = new \DateTime();
        $this->output->writeln("<info>[{$now->format('Y-m-d H:i:s')}]: {$message}</info>");
    }
}
