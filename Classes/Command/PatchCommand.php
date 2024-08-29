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

class PatchCommand extends Command
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function configure()
    {
        $this
            ->setHelp(<<<HEREDOC
Modify database records with the DataHandler. You need to provide --whereClause or --records
Examples:

Pages with title 'Detail' should not be included in search

    ./bin/typo3 datahandler:patch --table pages --whereClause 'title="Detail"' --jsonPatch '{"no_search": 1}'

Page #2 should become an external link to typo3.org

   ./bin/typo3 datahandler:patch --records 2 --table pages --jsonPatch '{"doktype": 3, "url": "https://typo3.org"}'

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
            )
            ->addOption(
                'jsonPatch',
                'p',
                InputOption::VALUE_REQUIRED,
                'For example {"hidden": 1}'
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

        $jsonPatch = $input->getOption('jsonPatch');
        if (!$jsonPatch) {
            $jsonPatch = $io->ask('JSON Patch');
        }
        try {
            $patch = json_decode($jsonPatch, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $output->writeln('<error>Invalid jsonPatch: ' . $exception->getMessage() . '</error>');
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

        $output->writeln('<info>Patching ' . count($records) . ' records</info>');

        $data = [];
        foreach ($records as $record) {
            $data[$table][$record] = $patch;
        }

        Bootstrap::initializeBackendAuthentication();
        $dataHandler = $this->getDataHandler();
        $dataHandler->start($data, []);
        $dataHandler->admin = true;
        if ($workspaceId) {
            $dataHandler->BE_USER->workspace = $workspaceId;
        }
        $dataHandler->process_datamap();
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
