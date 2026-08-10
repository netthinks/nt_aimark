<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Command;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Model\StorageSummary;
use NetThinks\NtAimark\Domain\Repository\TransparencyRepository;
use NetThinks\NtAimark\Report\SystemStatusCheck;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;

/**
 * Reports what is still waiting for a decision.
 *
 * Meant for the scheduler: the open items only shrink if somebody is reminded
 * that they exist.
 */
final class ReportCommand extends Command
{
    public function __construct(
        private readonly TransparencyRepository $repository,
        private readonly SystemStatusCheck $systemStatusCheck,
        private readonly MailerInterface $mailer,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Reports files whose AI involvement has not been decided yet.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Send the report to this address')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many files to list', '25')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the report, send no mail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));

        $summaries = $this->repository->storageSummaries();
        $open = $this->repository->findAssets(
            [AiStatus::Unreviewed->value, AiStatus::Suggested->value],
            limit: $limit,
        );
        $openTotal = $this->repository->countAssets([AiStatus::Unreviewed->value, AiStatus::Suggested->value]);

        $io->title('AI transparency — open items');

        if ($summaries === []) {
            $io->success('No files found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Storage', 'Files', 'Reviewed', 'Open', 'Broken signature'],
            array_map(
                static fn(StorageSummary $summary): array => [
                    $summary->storageName,
                    $summary->total,
                    sprintf('%d (%d %%)', $summary->getReviewed(), $summary->getReviewedPercent()),
                    $summary->getOpen(),
                    $summary->brokenC2pa,
                ],
                $summaries,
            ),
        );

        if ($open !== []) {
            $io->section(sprintf('Waiting for a decision (%d of %d)', count($open), $openTotal));
            $io->listing(array_map(
                static fn(array $row): string => (string) ($row['identifier'] ?? $row['name'] ?? '?'),
                $open,
            ));
        }

        // There is no LanguageService on the CLI, so labels would otherwise
        // reach the operator as raw LLL keys.
        $language = $this->languageServiceFactory->create('default');

        foreach ($this->systemStatusCheck->findings() as $finding) {
            if ($finding['severity'] === SystemStatusCheck::SEVERITY_WARNING) {
                $io->warning(sprintf(
                    '%s — %s %s',
                    $language->sL($finding['titleKey']),
                    $language->sL($finding['detailKey']),
                    $finding['detail'],
                ));
            }
        }

        $email = $input->getOption('email');

        if (is_string($email) && $email !== '') {
            if ((bool) $input->getOption('dry-run')) {
                $io->note(sprintf('Dry run — no mail sent to %s.', $email));
            } else {
                $this->send($email, $openTotal, $summaries, $io);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<StorageSummary> $summaries
     */
    private function send(string $email, int $openTotal, array $summaries, SymfonyStyle $io): void
    {
        $lines = [sprintf('%d file(s) still need a decision.', $openTotal), ''];

        foreach ($summaries as $summary) {
            $lines[] = sprintf(
                '%s: %d of %d reviewed (%d %%), %d open, %d with a broken signature',
                $summary->storageName,
                $summary->getReviewed(),
                $summary->total,
                $summary->getReviewedPercent(),
                $summary->getOpen(),
                $summary->brokenC2pa,
            );
        }

        try {
            $mail = new MailMessage();
            $mail->to($email)
                ->subject(sprintf('AI transparency: %d open item(s)', $openTotal))
                ->text(implode("\n", $lines));

            $this->mailer->send($mail);

            $io->success(sprintf('Report sent to %s.', $email));
        } catch (\Throwable $exception) {
            // A report that cannot be delivered is worth an exit code, but the
            // figures above were printed and are not lost.
            $io->error(sprintf('Could not send the report: %s', $exception->getMessage()));
        }
    }
}
