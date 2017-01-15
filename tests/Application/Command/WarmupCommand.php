<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Tests\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Application\Mock\PhpMockBuilder;

/**
 * Class WarmupCommand
 */
class WarmupCommand extends Command
{
    /** {@inheritdoc} */
    protected function configure()
    {
        parent::configure();
        $this->setName('async_sockets:test:warmup')
             ->setDescription('Prepare internal cache for testing')
            ->addOption(
                'configuration',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to config file relative to library root dir',
                'config.yml'
            );
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootDir       = __DIR__ . '/../../../';
        $configuration = new Configuration($rootDir, $input->getOption('configuration'));
        $cachePath     = $configuration->cacheDir();
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $cachePath = realpath($cachePath);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $rootDir . 'src'
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $builder = new PhpMockBuilder($cachePath);
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $builder->build($file->getRealPath());
            }
        }

        $builder->flush();
    }
}
