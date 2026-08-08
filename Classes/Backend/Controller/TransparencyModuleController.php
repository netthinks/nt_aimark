<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Backend\Controller;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
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

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'summaries' => $this->repository->storageSummaries(),
            'assets' => $this->decorate($rows),
            // Fluid resolves {item.labelKey} via getLabelKey(); enum methods
            // are not reachable that way, so plain arrays go to the template.
            'statusItems' => $this->statusItems($filter['statuses']),
            'filter' => $filter,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / self::PAGE_SIZE),
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
            $rows[$index]['needsReview'] = $status->requiresReview();
            $rows[$index]['editUri'] = (string) $this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['sys_file_metadata' => [(int) $row['uid'] => 'edit']],
                'returnUrl' => (string) $this->uriBuilder->buildUriFromRoute('content_ntaimark_transparency'),
            ]);
        }

        return $rows;
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
            'storage' => max(0, (int) ($parameters['storage'] ?? 0)),
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
