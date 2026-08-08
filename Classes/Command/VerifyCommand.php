<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Command;

use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Repository\TransparencyRepository;
use NetThinks\NtAimark\Service\AuditService;
use NetThinks\NtAimark\Service\C2paService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Re-checks the Content Credentials of files that carry them.
 *
 * A signature that was valid at upload can break later — a file replaced
 * outside TYPO3, a deployment that rewrote images. The recorded state would
 * then claim something about the file that is no longer true.
 */
final class VerifyCommand extends Command
{
    public function __construct(
        private readonly TransparencyRepository $repository,
        private readonly C2paService $c2paService,
        private readonly ResourceFactory $resourceFactory,
        private readonly AuditService $auditService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Re-validates the C2PA signatures of files that carry one.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what changed, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if (!$this->c2paService->isAvailable()) {
            $io->warning('c2patool is not available — nothing can be verified. Recorded states are left as they are.');

            return Command::SUCCESS;
        }

        $rows = $this->repository->findWithC2paState();

        if ($rows === []) {
            $io->success('No file carries Content Credentials.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Re-checking %d file(s)%s', count($rows), $dryRun ? ' (dry run)' : ''));

        $changes = [];
        $broken = 0;

        foreach ($rows as $row) {
            $previous = C2paState::tryFrom((int) $row['tx_ntaimark_c2pa_state']) ?? C2paState::None;

            try {
                $file = $this->resourceFactory->getFileObject((int) $row['file']);
                $current = $this->c2paService->inspect($file->getForLocalProcessing(false))->c2paState;
            } catch (\Throwable) {
                continue;
            }

            if ($current === C2paState::Broken) {
                $broken++;
            }

            if ($current === $previous) {
                continue;
            }

            $changes[] = [(string) $row['identifier'], $previous->name, $current->name];

            if ($dryRun) {
                continue;
            }

            $file->getMetaData()->add(['tx_ntaimark_c2pa_state' => $current->value])->save();

            $this->auditService->log(
                'sys_file_metadata',
                (int) $row['uid'],
                'verify',
                AuditService::SOURCE_CLI,
                'tx_ntaimark_c2pa_state',
                $previous->value,
                $current->value,
            );
        }

        if ($changes !== []) {
            $io->section('Changed');
            $io->table(['File', 'Previously', 'Now'], $changes);
        }

        $io->definitionList(
            ['Checked' => count($rows)],
            ['Changed' => count($changes)],
            ['Broken signatures' => $broken],
        );

        if ($dryRun) {
            $io->note('Dry run — nothing was written.');
        }

        // A broken signature is a finding, not a failure of this command.
        if ($broken > 0) {
            $io->warning(sprintf('%d file(s) carry a signature that no longer matches the file.', $broken));
        }

        return Command::SUCCESS;
    }
}
