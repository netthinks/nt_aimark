<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Backend\Controller;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Model\StorageSummary;
use NetThinks\NtAimark\Domain\Repository\TransparencyRepository;
use NetThinks\NtAimark\Report\SystemStatusCheck;
use NetThinks\NtAimark\Service\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Backend module "AI transparency".
 *
 * Shows how far the review has come, lists what is still open, and lets an
 * editor settle several assets at once. Every change made here is written to
 * the audit trail — the module exists to produce evidence, not just to save
 * clicks.
 */
#[AsController]
final readonly class TransparencyModuleController
{
    private const LL = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_mod.xlf:';

    private const PAGE_SIZE = 50;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private TransparencyRepository $repository,
        private SystemStatusCheck $systemStatusCheck,
        private AuditService $auditService,
        private ResourceFactory $resourceFactory,
        private UriBuilder $uriBuilder,
        private FlashMessageService $flashMessageService,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getQueryParams();
        $filter = $this->filterFrom($parameters);
        $page = max(1, (int) ($parameters['page'] ?? 1));

        $total = $this->repository->countAssets(
            $filter['statuses'],
            $filter['storage'],
            $filter['createdAfter'],
            $filter['createdBefore'],
        );

        $rows = $this->repository->findAssets(
            $filter['statuses'],
            $filter['storage'],
            $filter['createdAfter'],
            $filter['createdBefore'],
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE,
        );

        $summaries = $this->repository->storageSummaries();
        $totals = $this->totals($summaries);
        $pages = (int) ceil($total / self::PAGE_SIZE);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'summaries' => $summaries,
            // One set of figures for the whole installation. Per storage the
            // numbers answer "where is the work"; added up they answer "how
            // far along are we", which is the question the module opens with.
            'totals' => $totals,
            'segments' => $this->chartSegments($this->repository->statusDistribution(), $totals['total']),
            'pagination' => $this->pagination($page, $pages, $parameters),
            'filterValues' => [
                'createdAfter' => $filter['createdAfter'] > 0 ? date('Y-m-d', $filter['createdAfter']) : '',
                'createdBefore' => $filter['createdBefore'] > 0 ? date('Y-m-d', $filter['createdBefore']) : '',
                'storage' => $filter['storage'],
            ],
            'storageItems' => $this->storageItems($summaries, $filter['storage']),
            'resetUri' => (string) $this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency'),
            'assets' => $this->decorate($rows),
            // Fluid resolves {item.labelKey} via getLabelKey(); enum methods
            // are not reachable that way, so plain arrays go to the template.
            'statusItems' => $this->statusItems($filter['statuses']),
            'filter' => $filter,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'findings' => $this->systemStatusCheck->findings(),
            'bulkUri' => (string) $this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency.bulk'),
        ]);

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    /**
     * Sets one status on several assets, with one audit entry per asset.
     */
    public function bulkAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getParsedBody();
        $parameters = is_array($parameters) ? $parameters : [];

        $uids = array_values(array_map(intval(...), (array) ($parameters['uids'] ?? [])));
        $status = AiStatus::tryFrom((int) ($parameters['status'] ?? -1));

        if ($status === null || $uids === []) {
            $this->addMessage(self::LL . 'bulk.nothing', ContextualFeedbackSeverity::WARNING);

            return new RedirectResponse($this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency'));
        }

        // Setting a status here IS the human confirmation, so it may overwrite
        // an automatic suggestion — but the previous value goes on record.
        $changed = $this->applyStatus($uids, $status);

        $this->addMessage(
            self::LL . 'bulk.done',
            ContextualFeedbackSeverity::OK,
            [$changed],
        );

        return new RedirectResponse($this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency'));
    }

    /**
     * @param list<int> $uids
     */
    private function applyStatus(array $uids, AiStatus $status): int
    {
        $records = $this->repository->findByUids($uids);
        $userId = (int) ($this->backendUser()?->getUserId() ?? 0);
        $changed = 0;

        foreach ($records as $uid => $record) {
            $previous = (int) ($record['tx_ntaimark_status'] ?? 0);

            if ($previous === $status->value) {
                continue;
            }

            $file = $this->fileForMetadata($record);

            if ($file === null) {
                continue;
            }

            // Logged before the write, deliberately: the generic listener
            // compares against the trail, so an entry that is already there
            // keeps it from recording the same change a second time with less
            // context ("update" instead of "bulk_review").
            $this->auditService->log(
                'sys_file_metadata',
                $uid,
                'bulk_review',
                AuditService::SOURCE_MANUAL,
                'tx_ntaimark_status',
                $previous,
                $status->value,
            );

            $file->getMetaData()->add([
                'tx_ntaimark_status' => $status->value,
                'tx_ntaimark_reviewer' => $userId,
                'tx_ntaimark_reviewed_at' => time(),
            ])->save();

            $changed++;
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function fileForMetadata(array $record): ?\TYPO3\CMS\Core\Resource\File
    {
        $fileUid = (int) ($record['file'] ?? 0);

        if ($fileUid <= 0) {
            return null;
        }

        try {
            return $this->resourceFactory->getFileObject($fileUid);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function decorate(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $status = AiStatus::tryFrom((int) ($row['tx_ntaimark_status'] ?? 0)) ?? AiStatus::Unreviewed;
            $rows[$index]['statusLabelKey'] = $status->labelKey();
            $rows[$index]['statusBadgeClass'] = $status->badgeClass();
            $rows[$index]['needsReview'] = $status->requiresReview();
            $rows[$index]['editUri'] = (string) $this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['sys_file_metadata' => [(int) $row['uid'] => 'edit']],
                'returnUrl' => (string) $this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency'),
            ]);
        }

        return $rows;
    }

    /**
     * Ring segments for the status chart.
     *
     * The circle is drawn with a radius whose circumference is exactly 100, so
     * a percentage can be used as a dash length without further arithmetic in
     * the template. Everything lands in presentation attributes — the chart
     * must not depend on inline CSS, which a Content Security Policy with a
     * nonce drops without a word.
     *
     * @param array<int, int> $distribution
     *
     * @return list<array{labelKey: string, value: int, percent: float, percentLabel: string, colour: string, dashArray: string, dashOffset: string}>
     */
    private function chartSegments(array $distribution, int $total): array
    {
        if ($total <= 0) {
            return [];
        }

        $segments = [];
        $covered = 0.0;

        foreach (AiStatus::cases() as $case) {
            $value = $distribution[$case->value] ?? 0;

            if ($value === 0) {
                continue;
            }

            $percent = round($value / $total * 100, 3);

            $segments[] = [
                'labelKey' => $case->labelKey(),
                'value' => $value,
                'percent' => $percent,
                // Three decimals are what the ring geometry needs; one is what
                // a legend can be read at.
                'percentLabel' => number_format($percent, 1),
                'colour' => $case->chartColour(),
                'dashArray' => $percent . ' ' . round(100 - $percent, 3),
                // 25 puts the start of the ring at twelve o'clock.
                'dashOffset' => (string) round(25 - $covered, 3),
            ];

            $covered += $percent;
        }

        return $segments;
    }

    /**
     * @param list<StorageSummary> $summaries
     *
     * @return array{total: int, reviewed: int, open: int, brokenC2pa: int, reviewedPercent: int, openPercent: int}
     */
    private function totals(array $summaries): array
    {
        $total = $reviewed = $open = $broken = 0;

        foreach ($summaries as $summary) {
            $total += $summary->total;
            $reviewed += $summary->getReviewed();
            $open += $summary->getOpen();
            $broken += $summary->brokenC2pa;
        }

        return [
            'total' => $total,
            'reviewed' => $reviewed,
            'open' => $open,
            'brokenC2pa' => $broken,
            'reviewedPercent' => $reviewedPercent = $total > 0 ? (int) round($reviewed / $total * 100) : 0,
            // The remainder of the ring, so the template needs no arithmetic.
            'openPercent' => 100 - $reviewedPercent,
        ];
    }

    /**
     * Page links that keep the current filter — without them the list stops
     * at the first fifty files with no way on.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array{current: int, pages: int, previousUri: string, nextUri: string}
     */
    private function pagination(int $page, int $pages, array $parameters): array
    {
        $uriFor = function (int $target) use ($parameters): string {
            $query = $parameters;
            $query['page'] = $target;
            unset($query['token']);

            return (string) $this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency', $query);
        };

        return [
            'current' => $page,
            'pages' => $pages,
            'previousUri' => $page > 1 ? $uriFor($page - 1) : '',
            'nextUri' => $page < $pages ? $uriFor($page + 1) : '',
        ];
    }

    /**
     * @param list<StorageSummary> $summaries
     *
     * @return list<array{value: int, label: string, selected: bool}>
     */
    private function storageItems(array $summaries, int $selected): array
    {
        $items = [];

        foreach ($summaries as $summary) {
            $items[] = [
                'value' => $summary->storageUid,
                'label' => $summary->storageName,
                'selected' => $summary->storageUid === $selected,
            ];
        }

        return $items;
    }

    /**
     * @param list<int> $selected
     *
     * @return list<array{value: int, labelKey: string, selected: bool}>
     */
    private function statusItems(array $selected): array
    {
        return array_map(
            static fn(AiStatus $case): array => [
                'value' => $case->value,
                'labelKey' => $case->labelKey(),
                'selected' => in_array($case->value, $selected, true),
            ],
            AiStatus::cases(),
        );
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array{statuses: list<int>, storage: int, createdAfter: int, createdBefore: int}
     */
    private function filterFrom(array $parameters): array
    {
        $statuses = (array) ($parameters['status'] ?? []);

        return [
            'statuses' => array_values(array_filter(
                array_map(intval(...), $statuses),
                static fn(int $status): bool => AiStatus::tryFrom($status) !== null,
            )),
            // -1 means "every storage"; 0 cannot serve as that sentinel
            // because FAL uses storage 0 for files outside any storage.
            'storage' => isset($parameters['storage']) && $parameters['storage'] !== ''
                ? max(-1, (int) $parameters['storage'])
                : -1,
            'createdAfter' => $this->toTimestamp($parameters['createdAfter'] ?? ''),
            'createdBefore' => $this->toTimestamp($parameters['createdBefore'] ?? ''),
        ];
    }

    private function toTimestamp(mixed $value): int
    {
        if (!is_string($value) || trim($value) === '') {
            return 0;
        }

        try {
            return (new \DateTimeImmutable(trim($value)))->getTimestamp();
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * @param list<int|string> $arguments
     */
    private function addMessage(string $labelKey, ContextualFeedbackSeverity $severity, array $arguments = []): void
    {
        $language = $GLOBALS['LANG'] ?? null;
        $message = $language !== null ? $language->sL($labelKey) : $labelKey;

        if ($arguments !== []) {
            $message = vsprintf($message, $arguments);
        }

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->enqueue(new FlashMessage($message, '', $severity, true));
    }

    private function backendUser(): ?BackendUserAuthentication
    {
        $user = $GLOBALS['BE_USER'] ?? null;

        return $user instanceof BackendUserAuthentication ? $user : null;
    }
}
