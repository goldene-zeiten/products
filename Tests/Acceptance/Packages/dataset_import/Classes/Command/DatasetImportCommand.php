<?php

declare(strict_types=1);

namespace GoldeneZeiten\ProductsDatasetImport\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;

/**
 * Seeds the disposable acceptance-test instance from a hand-maintained, functional-test-style
 * CSV fixture - the same DataSet class Tests/Functional/*Test.php already use via
 * importCSVDataSet(), reused here against a live database instead of a test transaction.
 */
#[AsCommand('dataset:import', 'Import a functional-test-style CSV dataset into this instance')]
final class DatasetImportCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the CSV dataset to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getArgument('path');
        DataSet::import($path);
        $output->writeln(sprintf('Imported dataset from "%s".', $path));
        return Command::SUCCESS;
    }
}
