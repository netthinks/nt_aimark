<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Command;

use NetThinks\NtAimark\Domain\Repository\TransparencyRepository;
use NetThinks\NtAimark\Service\ProvenanceExtractorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Re-examines stored files for provenance data.
 *
 * Useful after installing c2patool, after adding EXIF signatures, or simply to
 * take stock of an existing library. What it finds is a suggestion; a record a
 * human has confirmed is never touched, whatever options are passed.
 */
final class ScanCommand extends Command
{
    public function __construct(
        private readonly TransparencyRepository $repository,
        private readonly ProvenanceExtractorService $provenanceExtractor,
        private readonly ResourceFactory $resourceFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Examines stored files for AI provenance data and records what it finds as a suggestion.')
            ->addOption('storage', null, InputOption::VALUE_REQUIRED, 'Limit to one file storage', '0')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Also re-examine files that already carry a suggestion. Confirmed records stay untouched either way.',
            )
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many files', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would happen, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $storage = (int) $input->getOption('storage');
        $limit = max(0, (int) $input->getOption('limit'));

        $fileUids = $this->repository->findFileUidsForScan($storage, (bool) $input->getOption('force'));

        if ($limit > 0) {
            $fileUids = array_slice($fileUids, 0, $limit);
        }

        if ($fileUids === []) {
            $io->success('Nothing to examine.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Examining %d file(s)%s', count($fileUids), $dryRun ? ' (dry run)' : ''));
        $io->progressStart(count($fileUids));

        $found = 0;
        $failed = 0;

        foreach ($fileUids as $fileUid) {
            try {
                $file = $this->resourceFactory->getFileObject($fileUid);

                if ($dryRun) {
                    $result = $this->provenanceExtractor->extract($file->getForLocalProcessing(false));
                    $found += $result->hasAnything() ? 1 : 0;
                } else {
                    $found += $this->provenanceExtractor->applyTo($file) ? 1 : 0;
                }
            } catch (\Throwable) {
                // One unreadable file must not end the run.
                $failed++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->definitionList(
            ['Examined' => count($fileUids)],
            ['Findings' => $found],
            ['Unreadable' => $failed],
        );

        if ($dryRun) {
            $io->note('Dry run — nothing was written.');
        }

        $io->success('Done. Every finding is a suggestion and still needs confirming.');

        return Command::SUCCESS;
    }
}
