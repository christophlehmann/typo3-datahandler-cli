<?php
namespace Lemming\DataHandlerCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeleteCommand extends Command
{
    public function configure()
    {
        $this
            ->setHelp(<<<HEREDOC
Delete database records with the DataHandler. You must provide --whereClause or --records
Examples:

Pages with title 'Detail' should be deleted

    ./bin/typo3 datahandler:delete --table pages --whereClause 'title="Detail"'

Page #2 should be deleted

   ./bin/typo3 datahandler:delete --table pages --records 2

HEREDOC)
            ->addOption(
                'table',
                't',
                InputOption::VALUE_REQUIRED,
                'Records table name'
            )
            ->addOption(
                'whereClause',
                '',
                InputOption::VALUE_REQUIRED,
                'An SQL WHERE clause to build the record list'
            )
            ->addOption(
                'records',
                'r',
                InputOption::VALUE_REQUIRED,
                'Comma separated list of record ids'
            )
            ->addOption(
                'workspace',
                'w',
                InputOption::VALUE_REQUIRED,
                'Apply patch in this workspace id'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $whereClause = $input->getOption('whereClause');
        $recordList = $input->getOption('records');
        if (!$whereClause && !$recordList) {
            $output->writeln('<error>You must provide --records or --whereClause</error>');
            return Command::INVALID;
        }

        $table = $input->getOption('table');
        if (!$table) {
            $table = $io->ask('Table name');
        }
        if (!isset($GLOBALS['TCA'][$table])) {
            $output->writeln('<error>Table does not exist</error>');
            return Command::INVALID;
        }

        $workspaceId = $input->getOption('workspace');
        if ($workspaceId) {
            $workspace = BackendUtility::getRecord('sys_workspace', $workspaceId);
            if (!$workspace) {
                $output->writeln('<error>Workspace does not exist</error>');
                return Command::INVALID;
            }
        }

        if (!empty($whereClause)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($table);
            $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $records = $queryBuilder
                ->select('uid')
                ->from($table)
                ->where($whereClause)
                ->executeQuery()
                ->fetchFirstColumn();
        } else {
            $records = GeneralUtility::intExplode(',', $recordList, true);
            $records = array_unique($records);
        }

        $output->writeln('<info>Deleting ' . count($records) . ' records</info>');

        $cmd = [];
        foreach ($records as $record) {
            $cmd[$table][$record]['delete'] = 1;
        }

        Bootstrap::initializeBackendAuthentication();
        $dataHandler = $this->getDataHandler();
        $dataHandler->start([], $cmd);
        $dataHandler->admin = true;
        if ($workspaceId) {
            $dataHandler->BE_USER->workspace = $workspaceId;
        }
        $dataHandler->process_cmdmap();
        foreach ($dataHandler->errorLog as $error) {
            $output->writeln('<error>' . $error . '</error>');
        }
        return Command::SUCCESS;
    }

    protected function getDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }
}